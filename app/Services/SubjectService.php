<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubjectService
{
    public function buildSubjectTreeFromCollection(Collection $subjects): array
    {
        $rootKey = 'root';
        $grouped = $subjects->groupBy(function ($subject) use ($rootKey) {
            return empty($subject->parent_id) ? $rootKey : (string) $subject->parent_id;
        });

        $buildTree = function (string $parentKey) use (&$buildTree, $grouped): array {
            $children = $grouped->get($parentKey, collect());

            return $children->map(function ($subject) use (&$buildTree) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'parent_id' => $subject->parent_id,
                    'children' => $buildTree((string) $subject->id),
                ];
            })->values()->all();
        };

        return $buildTree($rootKey);
    }

    public function buildSubjectTreeForRootSelection(?int $selectedRootId, int $maxRoots = 5): array
    {
        $roots = Subject::whereIsRoot()->orderBy('code')->get(['id', 'name', 'code', 'parent_id']);

        if ($roots->isEmpty()) {
            return [];
        }

        if ($roots->count() <= $maxRoots) {
            $selectedRoots = $roots;
        } else {
            $selectedRoots = $roots->take($maxRoots);

            if ($selectedRootId && ! $selectedRoots->contains('id', $selectedRootId)) {
                $selectedRoots = $roots->take(max($maxRoots - 1, 0));
                $selectedRoot = $roots->firstWhere('id', $selectedRootId)
                    ?? Subject::find($selectedRootId);

                if ($selectedRoot) {
                    $selectedRoots = $selectedRoots->push($selectedRoot);
                }
            }
        }

        $rootCodes = $selectedRoots->pluck('code')->unique()->values();

        if ($rootCodes->isEmpty()) {
            return [];
        }

        $subjects = Subject::query()->select(['id', 'name', 'code', 'parent_id'])
            ->where(function ($query) use ($rootCodes) {
                foreach ($rootCodes as $code) {
                    $query->orWhere('code', 'like', $code.'%');
                }
            })->orderBy('code')->get();

        return $this->buildSubjectTreeFromCollection($subjects);
    }

    public function sumSubjectWithDateRange(?Subject $subject)
    {
        if (is_null($subject)) {
            return [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0];
        }

        $year = (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));

        $months = [
            1 => [1, 31],
            2 => [1, 31],
            3 => [1, 31],
            4 => [1, 31],
            5 => [1, 31],
            6 => [1, 31],
            7 => [1, 30],
            8 => [1, 30],
            9 => [1, 30],
            10 => [1, 30],
            11 => [1, 30],
            12 => [1, 29],
        ];

        $subjectIds = $subject->getAllDescendantIds();
        $transactionQuery = Transaction::query()->whereIn('subject_id', $subjectIds);
        $monthlySum = [];

        foreach ($months as $month => [$startDay, $endDay]) {
            $startDate = jalali_to_gregorian($year, $month, $startDay, '-');
            $endDate = jalali_to_gregorian($year, $month, $endDay, '-');

            $transactions = (clone $transactionQuery)
                ->join('documents', 'documents.id', '=', 'transactions.document_id')
                ->whereBetween('documents.date', [$startDate, $endDate])
                ->selectRaw('DATE(documents.date) as date, SUM(transactions.value) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date')
                ->map(fn ($v) => (int) $v);

            foreach ($transactions as $date => $total) {
                $monthlySum[$month] = ($monthlySum[$month] ?? 0) + $total;
            }
        }

        return $monthlySum;
    }

    /**
     * Calculate the total sum of transactions for a subject with all its descendants recursively.
     */
    public static function sumSubject(string|int|Subject|null $code, bool $both = true, bool $debit = false): float
    {
        if (is_null($code)) {
            return 0;
        } elseif ($code instanceof Subject) {
            $subject = $code->loadMissing(['transactions']);
        } elseif (is_int($code)) {
            $subject = Subject::with(['transactions'])->find($code);
        } else {
            $subject = Subject::with(['transactions'])->where('code', $code)->first();
        }

        if (! $subject) {
            return 0;
        }

        self::eagerLoadDescendants($subject);

        return self::sumSubjectRecursively($subject, $both, $debit);
    }

    /**
     * Recursively sum transactions for a subject and all its descendants
     */
    private static function sumSubjectRecursively(Subject $subject, bool $both, bool $debit): float
    {
        $sum = 0.0;
        if ($both) {
            $sum = $subject->transactions->sum('value');
        } elseif ($debit) {
            $sum = $subject->transactions->where('value', '<', 0)->sum('value');
        } else {
            $sum = $subject->transactions->where('value', '>', 0)->sum('value');
        }

        $children = $subject->children()->with('transactions')->get();
        /** @var Subject $child */
        foreach ($children as $child) {
            $sum += self::sumSubjectRecursively($child, $both, $debit);
        }

        return $sum;
    }

    /**
     * Recursively eager-load all descendants with their transactions.
     */
    private static function eagerLoadDescendants(Subject $subject): void
    {
        $subject->loadMissing(['children' => function ($query) {
            $query->with('transactions');
        }]);

        foreach ($subject->children as $child) {
            self::eagerLoadDescendants($child);
        }
    }

    /**
     * Create a new Subject with only the required inputs.
     *
     * Accepted keys in $data:
     * - name (string, required)
     * - parent_id (int|null, optional)
     */
    public function createSubject(array $data): Subject
    {
        $name = $data['name'] ?? null;
        if (! $name) {
            throw new \InvalidArgumentException('The name field is required.');
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId === '' || $parentId === 0) {
            $parentId = null; // normalize to null for roots
        }

        $companyId = $data['company_id'] ?? getActiveCompany();
        if (! $companyId) {
            throw new \InvalidArgumentException('The company_id is required or must be available in session.');
        }

        $parentSubject = null;
        if ($parentId !== null) {
            $parentSubject = Subject::withoutGlobalScopes()->where('company_id', $companyId)->find($parentId);

            if (! $parentSubject) {
                throw new \InvalidArgumentException(__('Parent subject not found in the given company.'));
            }
        }

        if (isset($data['code']) && $data['code'] !== '') {
            $code = $this->buildCodeWithParent($data['code'], $parentId, (int) $companyId);
            $this->validateCodeUniqueness($code, (int) $companyId);
        } else {
            $code = $this->generateCode($parentId, (int) $companyId);
        }

        if (isset($data['type'], $data['is_permanent'])) {
            $resolvedType = $parentSubject ? $this->resolveTypeForParent($parentSubject, $data['type']) : ($data['type'] ?? 'both');
            $is_permanent = $parentSubject ? $parentSubject->is_permanent : ($data['is_permanent']);
        } else {
            $resolvedType = $parentSubject ? $this->resolveTypeForParent($parentSubject, null) : 'both';
            $is_permanent = $parentSubject ? $parentSubject->is_permanent : false;
        }

        $attributes = [
            'name' => $name,
            'parent_id' => $parentId,
            'company_id' => $companyId,
            'type' => $resolvedType,
            'code' => $code,
            'is_permanent' => $is_permanent,
        ];

        return Subject::create($attributes);
    }

    /**
     * Edit an existing Subject.
     *
     * Accepted keys in $data:
     * - name (string, optional)
     * - parent_id (int|null, optional) - will trigger code regeneration if changed
     * - type (string, optional) - 'debtor', 'creditor', or 'both'
     */
    public function editSubject(Subject $subject, array $data): Subject
    {
        if (! $subject->exists) {
            throw new \InvalidArgumentException(__('Subject does not exist.'));
        }

        $companyId = $subject->company_id;

        $parentIdChanged = array_key_exists('parent_id', $data) && $data['parent_id'] !== $subject->parent_id;

        if ($parentIdChanged) {
            $newParentId = $data['parent_id'];
            if ($newParentId === '' || $newParentId === 0) {
                $newParentId = null;
            }

            if ($newParentId !== null) {
                $descendantIds = $subject->getAllDescendantIds();
                if (in_array($newParentId, $descendantIds)) {
                    throw new \InvalidArgumentException(__('Cannot move a subject to one of its descendants.'));
                }

                $newParent = Subject::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->find($newParentId);
                if (! $newParent) {
                    throw new \InvalidArgumentException(__('New parent subject not found in the given company.'));
                }

                $data['is_permanent'] = $newParent->is_permanent;
                $data['type'] = $this->resolveTypeForParent($newParent, $data['type'] ?? null);
            }

            if (isset($data['code']) && ! empty($data['code'])) {
                $newCode = $this->buildCodeWithParent($data['code'], $newParentId, $companyId);
                $this->validateCodeUniqueness($newCode, $companyId, $subject->id);
            } else {
                $newCode = $this->generateCode($newParentId, $companyId);
            }
            $data['code'] = $newCode;

            $subject->update(array_intersect_key($data, array_flip(['name', 'parent_id', 'code', 'type', 'is_permanent'])));

            $this->updateDescendantCodesTypesIsPermanents($subject);
        } else {
            $allowedFields = array_intersect_key($data, array_flip(['name', 'code', 'type', 'is_permanent']));
            if (! empty($allowedFields)) {
                $oldCode = $subject->code;
                $oldType = $subject->type;
                $oldIsPermanent = $subject->is_permanent;

                if (isset($allowedFields['code']) && ! empty($allowedFields['code'])) {
                    $newCode = $this->buildCodeWithParent($allowedFields['code'], $subject->parent_id, $companyId);
                    $this->validateCodeUniqueness($newCode, $companyId, $subject->id);
                } else {
                    $newCode = $subject->code; // If code is not being changed, keep the old code to avoid unnecessary updates and descendant code regenerations
                }

                $allowedFields['code'] = $newCode;

                $subject->update($allowedFields);
                if ($allowedFields['code'] !== $oldCode || (isset($allowedFields['type']) && $allowedFields['type'] !== $oldType) || (isset($allowedFields['is_permanent']) && $allowedFields['is_permanent'] !== $oldIsPermanent)) {
                    $this->updateDescendantCodesTypesIsPermanents($subject);
                }
            }
        }

        return $subject->fresh();
    }

    private function resolveTypeForParent(Subject $parent, ?string $requestedType): string
    {
        if ($parent->type !== 'both') {
            return $parent->type;
        }

        return $requestedType ?? 'both';
    }

    /**
     * Recursively update codes, types and is_permanent for all descendants of a subject.
     */
    private function updateDescendantCodesTypesIsPermanents(Subject $subject): void
    {
        $children = $subject->children()->get();

        foreach ($children as $child) {
            $childOwnPortion = substr($child->code, -3);
            $newCode = $subject->code.$childOwnPortion;
            $newType = $this->resolveTypeForParent($subject, $child->type);
            $newIsPermanent = $subject->is_permanent;

            $child->update(['code' => $newCode, 'type' => $newType, 'is_permanent' => $newIsPermanent]);

            if ($child->hasChildren()) {
                $this->updateDescendantCodesTypesIsPermanents($child);
            }
        }
    }

    public function getAllowedTypesForSubject(?Subject $parentSubject): array
    {
        if (is_null($parentSubject) || $parentSubject->type === 'both') {
            return ['debtor', 'creditor', 'both'];
        }

        return [$parentSubject->type];
    }

    /**
     * Build a complete code by combining parent code with provided code portion.
     */
    private function buildCodeWithParent(string $codePortion, ?int $parentId, int $companyId): string
    {
        // Sanitize the code portion (remove any non-numeric characters)
        $codePortion = preg_replace('/[^0-9]/', '', $codePortion);

        if ($parentId) {
            $parent = Subject::withoutGlobalScopes()->where('company_id', $companyId)->find($parentId);
            if (! $parent) {
                throw new \InvalidArgumentException(__('Parent subject not found in the given company.'));
            }

            // Ensure the code portion is 3 digits
            if (strlen($codePortion) > 3) {
                throw new \InvalidArgumentException(__('Code portion cannot exceed 3 digits.'));
            }
            $codePortion = str_pad($codePortion, 3, '0', STR_PAD_LEFT);

            return $parent->code.$codePortion;
        }

        // Root subject - ensure it's 3 digits
        if (strlen($codePortion) > 3) {
            throw new \InvalidArgumentException('Root subject code cannot exceed 3 digits.');
        }

        return str_pad($codePortion, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Validate that the code is unique within the company.
     */
    private function validateCodeUniqueness(string $code, int $companyId, ?int $excludeId = null): void
    {
        $query = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $code);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException(__('The code :code already exists in this company.', ['code' => $code]));
        }
    }

    /**
     * Generate hierarchical subject code for the given company and parent.
     */
    private function generateCode(?int $parentId, int $companyId): string
    {
        if ($parentId) {
            $parent = Subject::withoutGlobalScopes()->where('company_id', $companyId)->find($parentId);
            if (! $parent) {
                throw new \InvalidArgumentException('Parent subject not found in the given company.');
            }

            $parentCode = $parent->code;
            $lastChild = Subject::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('parent_id', $parentId)
                ->orderBy('code', 'desc')
                ->first();

            if ($lastChild) {
                $childPart = substr($lastChild->code, -3);
                $next = (int) $childPart + 1;
                if ($next > 999) {
                    throw new \Exception("Maximum of 999 children reached for parent {$parentCode}");
                }

                $code = $parentCode.str_pad($next, 3, '0', STR_PAD_LEFT);

                try {
                    $this->validateCodeUniqueness($code, $companyId);
                } catch (\Exception $e) {
                    while (Subject::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists()) {
                        $next++;
                        if ($next > 999) {
                            throw new \Exception("Maximum of 999 children reached for parent {$parentCode}");
                        }
                        $code = $parentCode.str_pad($next, 3, '0', STR_PAD_LEFT);
                    }
                }

                return $code;
            }

            return $parentCode.'001';
        }

        // Root subject generation
        $lastRoot = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->orderBy('code', 'desc')
            ->first();

        $next = 1;
        if ($lastRoot) {
            $next = (int) $lastRoot->code + 1;
            if ($next > 999) {
                throw new \Exception('Maximum of 999 root subjects reached');
            }
        }

        return str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    private function syncSubjectableSubjectId(Subject $subject): void
    {
        if (is_null($subject->subjectable_type) || is_null($subject->subjectable_id)) {
            return;
        }

        $modelsWithSubjectId = [Customer::class, CustomerGroup::class, BankAccount::class];

        if (! in_array($subject->subjectable_type, $modelsWithSubjectId)) {
            return;
        }

        $subjectable = $subject->subjectable_type::withoutGlobalScopes()->find($subject->subjectable_id);
        if ($subjectable && in_array('subject_id', $subjectable->getFillable())) {
            $subjectable->updateQuietly(['subject_id' => $subject->id]);
        }
    }

    public function transferSubject(Subject $source, Subject $destination, bool $transferSubjectable = false, bool $removeSource = false): array
    {
        if ($source->id === $destination->id) {
            throw new \InvalidArgumentException(__('Source and destination subjects must be different.'));
        }

        $descendantIds = $source->getAllDescendantIds();
        if (in_array($destination->id, $descendantIds)) {
            throw new \InvalidArgumentException(__('Cannot transfer to a descendant of the source subject.'));
        }

        $year = (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
        $startDate = jalali_to_gregorian($year, 1, 1, '-');
        $endDate = now()->format('Y-m-d');

        $result = DB::transaction(function () use ($source, $destination, $startDate, $endDate, $transferSubjectable, $descendantIds) {
            if ($transferSubjectable && ! is_null($source->subjectable_type) && ! is_null($source->subjectable_id)) {
                $destination->subjectable_type = $source->subjectable_type;
                $destination->subjectable_id = $source->subjectable_id;
                $destination->save();

                $this->syncSubjectableSubjectId($destination);

                $source->subjectable_type = null;
                $source->subjectable_id = null;
                $source->save();
            }

            $subjectIds = array_merge([$source->id], $descendantIds);

            $query = Transaction::whereIn('subject_id', $subjectIds)->whereHas('document', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            });

            $sum = (clone $query)->sum('value');
            $count = $query->count();

            $query->update(['subject_id' => $destination->id]);

            return [
                'count' => $count,
                'sum' => $sum,
                'source' => $source,
                'destination' => $destination,
            ];
        });

        if ($removeSource && $source->exists) {
            try {
                $source->fresh()->delete();
                $result['source_removed'] = true;
            } catch (\Exception) {
                $result['source_removed'] = false;
            }
        }

        return $result;
    }

    public function transferSubjectToNewUnderParent(Subject $source, Subject $parentDestination, bool $transferSubjectable = false, bool $removeSource = false): array
    {
        if ($source->id === $parentDestination->id) {
            throw new \InvalidArgumentException(__('Source and parent destination subjects must be different.'));
        }

        $descendantIds = $source->getAllDescendantIds();
        if (in_array($parentDestination->id, $descendantIds)) {
            throw new \InvalidArgumentException(__('Cannot transfer to a descendant of the source subject.'));
        }

        $result = DB::transaction(function () use ($source, $parentDestination, $transferSubjectable) {
            $newSubject = Subject::create([
                'name' => $source->name,
                'code' => $this->generateCode($parentDestination->id, $source->company_id),
                'parent_id' => $parentDestination->id,
                'company_id' => $source->company_id,
                'type' => $this->resolveTypeForParent($parentDestination, $source->type),
                'is_permanent' => $parentDestination->is_permanent,
            ]);

            if ($transferSubjectable && ! is_null($source->subjectable_type) && ! is_null($source->subjectable_id)) {
                $newSubject->subjectable_type = $source->subjectable_type;
                $newSubject->subjectable_id = $source->subjectable_id;
                $newSubject->save();

                $this->syncSubjectableSubjectId($newSubject);

                $source->subjectable_type = null;
                $source->subjectable_id = null;
                $source->save();
            }

            $year = (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
            $startDate = jalali_to_gregorian($year, 1, 1, '-');
            $endDate = now()->format('Y-m-d');

            $query = Transaction::where('subject_id', $source->id)
                ->whereHas('document', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                });

            $sum = (clone $query)->sum('value');
            $count = $query->count();
            $query->update(['subject_id' => $newSubject->id]);

            return [
                'count' => $count,
                'sum' => $sum,
                'source' => $source,
                'destination' => $newSubject,
            ];
        });

        if ($removeSource && $source->exists) {
            try {
                $source->fresh()->delete();
                $result['source_removed'] = true;
            } catch (\Exception) {
                $result['source_removed'] = false;
            }
        }

        return $result;
    }
}
