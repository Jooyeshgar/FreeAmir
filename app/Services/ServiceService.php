<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    /**
     * Shared service search/filter used by the REST API list endpoints.
     */
    public static function searchQuery(array $filters = []): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $groupId = isset($filters['group_id']) && $filters['group_id'] !== null && $filters['group_id'] !== '' ? (int) $filters['group_id'] : null;

        $query = Service::query()
            ->with('serviceGroup:id,name');

        if ($groupId !== null && $groupId > 0) {
            $query->where('group', $groupId);
        }

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($search, $escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('code', 'like', "%{$escaped}%");

                if (ctype_digit($search) && (int) $search > 0) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        return $query->orderBy('code')->orderBy('id');
    }

    public static function formatSummary(Service $service): array
    {
        return [
            'id' => (int) $service->id,
            'name' => (string) $service->name,
            'code' => $service->code !== null ? (string) $service->code : null,
            'group_id' => $service->group !== null ? (int) $service->group : null,
            'group_name' => $service->serviceGroup?->name,
            'selling_price' => (float) ($service->selling_price ?? 0),
            'vat' => (float) ($service->vat ?? 0),
            'description' => $service->description,
        ];
    }

    /**
     * Create a service from the API payload, reusing an existing service with
     * the same identity (code/name/sstid) and refusing conflicting code reuse.
     * Runs inside a transaction with row locks so concurrent requests cannot
     * create duplicate services.
     *
     * @return array{service: Service, reused: bool}|array{conflict: string}
     */
    public function storeReusable(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $name = trim((string) $data['name']);
            $code = trim((string) $data['code']);
            $sstid = trim((string) ($data['sstid'] ?? ''));
            $group = (int) $data['group'];

            $existingByCode = Service::query()->where('code', $code)->lockForUpdate()->first();

            if ($existingByCode) {
                $sameIdentity = trim((string) $existingByCode->name) === $name
                    && trim((string) ($existingByCode->sstid ?? '')) === $sstid;

                if ($sameIdentity) {
                    return ['service' => $existingByCode, 'reused' => true];
                }

                return ['conflict' => 'code'];
            }

            $identityQuery = Service::query()
                ->where('name', $name)
                ->where('group', $group);

            if ($sstid !== '') {
                $identityQuery->where('sstid', $sstid);
            } else {
                $identityQuery->where(function ($q) {
                    $q->whereNull('sstid')->orWhere('sstid', '');
                });
            }

            $existingByIdentity = $identityQuery->lockForUpdate()->first();

            if ($existingByIdentity) {
                return ['service' => $existingByIdentity, 'reused' => true];
            }

            $service = $this->create([
                'name' => $name,
                'code' => $code,
                'sstid' => $sstid !== '' ? $sstid : null,
                'group' => $group,
                'selling_price' => $data['selling_price'] ?? 0,
                'description' => $data['description'] ?? null,
                'vat' => $data['vat'] ?? 0,
            ]);

            return ['service' => $service, 'reused' => false];
        });
    }

    public function create(array $data): Service
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($data['fiscal_year_id'] ?? getActiveFiscalYear());

        $data['fiscal_year_id'] ??= getActiveFiscalYear();

        $service = Service::create($data);

        $this->syncSubjects($service);

        return $service;
    }

    public function update(Service $service, array $data): Service
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($service, $data['fiscal_year_id'] ?? null);

        $service->fill($data);
        $service->save();

        $this->syncSubjects($service);

        return $service;
    }

    public function delete(Service $service): void
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($service);

        $service->delete();
        $this->deleteSubjects($service);
    }

    public function deleteSubjects(Service $service): void
    {
        $service->subject?->delete();
        $service->cogsSubject?->delete();
        $service->salesReturnsSubject?->delete();
    }

    protected function syncSubjects(Service $service): void
    {
        $service->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');

        $group = $service->serviceGroup;
        $fiscalYearId = $service->fiscal_year_id ?? $group?->fiscal_year_id ?? getActiveFiscalYear();

        if (! $fiscalYearId) {
            throw new \RuntimeException('Unable to determine fiscal year for service subject synchronization.');
        }

        $subjectConfigs = [
            'subject_id' => [
                'relation' => 'subject',
                'parent_column' => 'subject_id',
            ],
            'cogs_subject_id' => [
                'relation' => 'cogsSubject',
                'parent_column' => 'cogs_subject_id',
            ],
            'sales_returns_subject_id' => [
                'relation' => 'salesReturnsSubject',
                'parent_column' => 'sales_returns_subject_id',
            ],
        ];

        $updatedIds = [];

        foreach ($subjectConfigs as $column => $settings) {
            $relation = $settings['relation'];
            $subject = $service->$relation;
            $parentId = $group?->{$settings['parent_column']} ?? null;
            $targetName = $service->name;

            if (! $subject) {
                $subject = $this->subjectService->createSubject([
                    'name' => $targetName,
                    'parent_id' => $parentId,
                    'fiscal_year_id' => $fiscalYearId,
                ]);
            }

            $needsSave = false;

            if ($subject->name !== $targetName) {
                $subject->name = $targetName;
                $needsSave = true;
            }

            $normalizedParentId = $parentId ?: null;
            if ($subject->parent_id !== $normalizedParentId) {
                $subject->parent_id = $normalizedParentId;
                $needsSave = true;
            }

            if ($subject->subjectable_id !== $service->id || $subject->subjectable_type !== $service->getMorphClass()) {
                $subject->subjectable()->associate($service);
                $needsSave = true;
            }

            if ($needsSave) {
                $subject->save();
            }

            $service->setRelation($relation, $subject);
            $updatedIds[$column] = $subject->id;
        }

        $dirtyIds = [];

        foreach ($updatedIds as $column => $id) {
            if ($id !== $service->$column) {
                $dirtyIds[$column] = $id;
            }
        }

        if ($dirtyIds) {
            $service->updateQuietly($dirtyIds);
        }
    }

    public function totalCOGS(Service $service): float
    {
        return $this->subjectService->sumSubject($service->cogsSubject);
    }

    public function totalSalesReturns(Service $service): float
    {
        return $this->subjectService->sumSubject($service->salesReturnsSubject);
    }
}
