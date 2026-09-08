<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $data['company_id'] ??= getActiveCompany();
            $subjectCode = $data['subject_code'] ?? null;
            unset($data['subject_code']);

            $customer = Customer::create($data);
            $this->syncSubject($customer, $subjectCode);

            return $customer;
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
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
        $companyId = $customer->company_id ?? $group?->company_id ?? getActiveCompany();

        if (! $companyId) {
            throw new \RuntimeException('Unable to determine company for customer subject synchronization.');
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
            $existingSubject = Subject::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $resolvedCode)->first();

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

                $this->associateSubject($subject, $customer);

                return;
            }
        }

        if (! $subject) {
            $attributes = [
                'name' => $targetName,
                'parent_id' => $parentId,
                'company_id' => $companyId,
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
