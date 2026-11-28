<?php

namespace App\Services;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    /**
     * Shared customer search/filter used by both the REST API and the MCP read tools.
     */
    public static function searchQuery(array $filters = []): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $groupId = isset($filters['group_id']) && $filters['group_id'] !== null && $filters['group_id'] !== '' ? (int) $filters['group_id'] : null;
        $type = $filters['type'] ?? null;

        $query = Customer::query()
            ->with('group:id,name');

        if (! empty($type)) {
            $query->where('type', $type instanceof CustomerType ? $type->value : CustomerType::fromName((string) $type)->value);
        }

        if ($groupId !== null && $groupId > 0) {
            $query->where('group_id', $groupId);
        }

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($search, $escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('phone', 'like', "%{$escaped}%")
                    ->orWhere('mobile', 'like', "%{$escaped}%")
                    ->orWhere('personal_code', 'like', "%{$escaped}%")
                    ->orWhere('ecnmcs_code', 'like', "%{$escaped}%");

                if (ctype_digit($search) && (int) $search > 0) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        return $query->orderBy('name')->orderBy('id');
    }

    public static function formatSummary(Customer $customer): array
    {
        $rawType = $customer->getRawOriginal('type');
        $customerType = is_numeric($rawType) ? CustomerType::tryFrom((int) $rawType) : null;

        return [
            'id' => (int) $customer->id,
            'name' => (string) $customer->name,
            'type' => $customerType?->valueName(),
            'type_label' => $customerType?->label(),
            'group_id' => $customer->group_id !== null ? (int) $customer->group_id : null,
            'group_name' => $customer->group?->name,
            'phone' => $customer->phone ?: null,
            'mobile' => $customer->mobile ?: null,
            'personal_code' => $customer->personal_code ?: null,
            'ecnmcs_code' => $customer->ecnmcs_code ?: null,
            'credit_limit' => (float) ($customer->credit ?? 0),
        ];
    }

    public static function formatDetail(Customer $customer, string $currency): array
    {
        $balance = $customer->subject ? (float) SubjectService::sumSubject($customer->subject) : 0.0;
        [$status, $statusCode] = match (true) {
            $balance < 0 => [__('Debtor'), 'debtor'],
            $balance > 0 => [__('Creditor'), 'creditor'],
            default => [__('Settled (zero)'), 'settled'],
        };

        $absBalance = abs($balance);
        $formattedAbsBalance = fmod($absBalance, 1.0) == 0.0
            ? number_format($absBalance)
            : rtrim(rtrim(number_format($absBalance, 2, '.', ','), '0'), '.');
        $formattedBalance = $formattedAbsBalance.' '.$currency.' ('.$status.')';
        $creditLimit = (float) ($customer->credit ?? 0);
        $formattedCreditLimit = fmod($creditLimit, 1.0) == 0.0
            ? number_format($creditLimit)
            : rtrim(rtrim(number_format($creditLimit, 2, '.', ','), '0'), '.');

        return array_merge(self::formatSummary($customer), [
            'fax' => $customer->fax ?: null,
            'address' => $customer->address ?: null,
            'postal_code' => $customer->postal_code ?: null,
            'email' => $customer->email ?: null,
            'web_page' => $customer->web_page ?: null,
            'responsible' => $customer->responsible ?: null,
            'connector' => $customer->connector ?: null,
            'description' => $customer->desc ?: null,
            'introducer_id' => $customer->introducer_id !== null ? (int) $customer->introducer_id : null,
            'introducer_name' => $customer->introducer?->name,
            'subject_id' => $customer->subject?->id !== null ? (int) $customer->subject->id : null,
            'subject_code' => $customer->subject?->code,
            'balance' => $balance,
            'formatted_balance' => $formattedBalance,
            'balance_status' => $status,
            'balance_status_code' => $statusCode,
            'formatted_credit_limit' => $formattedCreditLimit.' '.$currency,
        ]);
    }

    public function create(array $data): Customer
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($data['fiscal_year_id'] ?? getActiveFiscalYear());

        return DB::transaction(function () use ($data) {
            $data['fiscal_year_id'] ??= getActiveFiscalYear();
            $subjectCode = $data['subject_code'] ?? null;
            unset($data['subject_code']);

            $customer = Customer::create($data);
            $this->syncSubject($customer, $subjectCode);

            return $customer;
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($customer, $data['fiscal_year_id'] ?? null);

        return DB::transaction(function () use ($customer, $data) {
            $subjectCode = $data['subject_code'] ?? null;
            unset($data['subject_code']);

            $customer->fill($data);
            $customer->save();

            $this->syncSubject($customer, $subjectCode);

            return $customer;
        });
    }

    public function delete(Customer $customer): void
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($customer);

        DB::transaction(function () use ($customer) {
            app(ActivityLogService::class)->deleteModels($customer->comments()->getQuery());
            $customer->delete();
            $customer->subject?->delete();
        });
    }

    protected function syncSubject(Customer $customer, ?string $subjectCode = null): void
    {
        $customer->loadMissing('group', 'subject');

        $group = $customer->group;
        $fiscalYearId = $customer->fiscal_year_id ?? $group?->fiscal_year_id ?? getActiveFiscalYear();

        if (! $fiscalYearId) {
            throw new \RuntimeException('Unable to determine fiscal year for customer subject synchronization.');
        }

        $subject = $customer->subject;
        $parentId = $group?->subject_id ? (int) $group->subject_id : null;
        $targetName = $customer->name;

        if (! $group?->subject || $parentId === null) {
            throw ValidationException::withMessages([
                'group_id' => __('Customer Subject is not configured. Please set it in configurations.'),
            ]);
        }

        $resolvedCode = $this->resolveSubjectCode($subjectCode, $group->subject);

        if ($resolvedCode !== null) {
            $existingSubject = Subject::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)->where('code', $resolvedCode)->first();

            if ($existingSubject) {
                if ((int) $existingSubject->parent_id !== $parentId) {
                    throw ValidationException::withMessages([
                        'subject_code' => __('The subject code :code is not a child of the selected customer group.', [
                            'code' => formatCode($resolvedCode),
                        ]),
                    ]);
                }

                $belongsToCustomer = $existingSubject->subjectable_type === $customer->getMorphClass()
                    && (int) $existingSubject->subjectable_id === (int) $customer->id;

                if ($existingSubject->subjectable_type !== null && ! $belongsToCustomer) {
                    throw ValidationException::withMessages([
                        'subject_code' => __('Subject code :code is already linked to another record.', [
                            'code' => formatCode($resolvedCode),
                        ]),
                    ]);
                }

                if (! $subject || $subject->isNot($existingSubject)) {
                    $this->detachSubject($subject, $customer);
                    $subject = $existingSubject;
                }

                if ($belongsToCustomer && $subject->name !== $targetName) {
                    $subject = $this->subjectService->editSubject($subject, ['name' => $targetName]);
                }

                $this->associateSubject($subject, $customer);

                return;
            }
        }

        if (! $subject) {
            $attributes = [
                'name' => $targetName,
                'parent_id' => $parentId,
                'fiscal_year_id' => $fiscalYearId,
            ];

            if ($resolvedCode !== null) {
                $attributes['code'] = substr($resolvedCode, -3);
            }

            $subject = $this->subjectService->createSubject($attributes);
        } else {
            // Delegate name/parent changes to SubjectService so the hierarchical
            // code (and any descendant codes) is regenerated when the group, and
            // therefore the parent subject, changes.
            $changes = [];

            if ($subject->name !== $targetName) {
                $changes['name'] = $targetName;
            }

            $currentParentId = $subject->parent_id !== null ? (int) $subject->parent_id : null;
            if ($currentParentId !== $parentId) {
                $changes['parent_id'] = $parentId;
            }

            if ($resolvedCode !== null && $subject->code !== $resolvedCode) {
                $changes['code'] = substr($resolvedCode, -3);
            }

            if ($changes !== []) {
                $subject = $this->subjectService->editSubject($subject, $changes);
            }
        }

        $this->associateSubject($subject, $customer);
    }

    private function resolveSubjectCode(?string $subjectCode, Subject $parent): ?string
    {
        if ($subjectCode === null || $subjectCode === '') {
            return null;
        }

        $subjectCode = preg_replace('/[^0-9]/', '', toEnglish($subjectCode));
        $resolvedCode = strlen($subjectCode) <= 3 ? $parent->code.str_pad($subjectCode, 3, '0', STR_PAD_LEFT) : $subjectCode;

        if (strlen($resolvedCode) !== strlen($parent->code) + 3 || ! str_starts_with($resolvedCode, $parent->code)) {
            throw ValidationException::withMessages([
                'subject_code' => __('The subject code :code is not a child of the selected customer group.', [
                    'code' => formatCode($resolvedCode),
                ]),
            ]);
        }

        return $resolvedCode;
    }

    private function detachSubject(?Subject $subject, Customer $customer): void
    {
        if ($subject && $subject->subjectable_type === $customer->getMorphClass() && (int) $subject->subjectable_id === (int) $customer->id) {
            $subject->forceFill([
                'subjectable_type' => null,
                'subjectable_id' => null,
            ])->saveQuietly();
        }
    }

    private function associateSubject(Subject $subject, Customer $customer): void
    {
        if ($subject->subjectable_id !== $customer->id || $subject->subjectable_type !== $customer->getMorphClass()) {
            $subject->subjectable()->associate($customer);
            $subject->save();
        }

        $customer->setRelation('subject', $subject);

        if ($subject->id !== $customer->subject_id) {
            $customer->updateQuietly(['subject_id' => $subject->id]);
        }
    }
}
