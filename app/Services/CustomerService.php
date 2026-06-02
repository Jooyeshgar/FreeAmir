<?php

namespace App\Services;

use App\Models\Customer;
use DB;

class CustomerService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): Customer
    {
        $data['company_id'] ??= getActiveCompany();

        // Optional explicit subject code portion (last 3 digits, relative to the group subject).
        // Used by the CSV importer; the normal create flow leaves it null and auto-generates.
        $subjectCode = $data['subject_code'] ?? null;
        unset($data['subject_code']);

        $customer = Customer::create($data);

        $this->syncSubject($customer, $subjectCode);

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill($data);
        $customer->save();

        $this->syncSubject($customer);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->comments()->delete();
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

        $relation = 'subject';
        $subject = $customer->$relation;
        $parentId = $group?->subject_id ? (int) $group->subject_id : null;
        $targetName = $customer->name;

        if (! $subject) {
            $attributes = [
                'name' => $targetName,
                'parent_id' => $parentId,
                'company_id' => $companyId,
            ];

            if ($subjectCode !== null && $subjectCode !== '') {
                $attributes['code'] = $subjectCode;
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

            if ($changes !== []) {
                $subject = $this->subjectService->editSubject($subject, $changes);
            }
        }

        if ($subject->subjectable_id !== $customer->id || $subject->subjectable_type !== $customer->getMorphClass()) {
            $subject->subjectable()->associate($customer);
            $subject->save();
        }

        $customer->setRelation($relation, $subject);

        if ($subject->id !== $customer->subject_id) {
            $customer->updateQuietly(['subject_id' => $subject->id]);
        }
    }
}
