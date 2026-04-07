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

        $customer = Customer::create($data);

        $this->syncSubject($customer);

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

    protected function syncSubject(Customer $customer): void
    {
        $customer->loadMissing('group', 'subject');

        $group = $customer->group;
        $companyId = $customer->company_id ?? $group?->company_id ?? getActiveCompany();

        if (! $companyId) {
            throw new \RuntimeException('Unable to determine company for customer subject synchronization.');
        }

        $relation = 'subject';
        $subject = $customer->$relation;
        $parentId = $group?->subject_id ?? null;
        $targetName = $customer->name;

        if (! $subject) {
            $subject = $this->subjectService->createSubject([
                'name' => $targetName,
                'parent_id' => $parentId,
                'company_id' => $companyId,
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

        if ($subject->subjectable_id !== $customer->id || $subject->subjectable_type !== $customer->getMorphClass()) {
            $subject->subjectable()->associate($customer);
            $needsSave = true;
        }

        if ($needsSave) {
            $subject->save();
        }

        $customer->setRelation($relation, $subject);

        if ($subject->id !== $customer->subject_id) {
            $customer->updateQuietly(['subject_id' => $subject->id]);
        }
    }
}
