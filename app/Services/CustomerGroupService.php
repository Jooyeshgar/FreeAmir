<?php

namespace App\Services;

use App\Models\CustomerGroup;
use DB;

class CustomerGroupService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): CustomerGroup
    {
        $data['company_id'] ??= getActiveCompany();

        $customerGroup = CustomerGroup::create($data);

        $this->syncSubject($customerGroup);

        return $customerGroup;
    }

    public function update(CustomerGroup $customerGroup, array $data): CustomerGroup
    {
        $customerGroup->fill($data);
        $customerGroup->save();

        $this->syncSubject($customerGroup);

        return $customerGroup;
    }

    public function delete(CustomerGroup $customerGroup): void
    {
        DB::transaction(function () use ($customerGroup) {
            foreach ($customerGroup->customers as $customer) {
                $customer->comments()->delete();
                $customer->delete();
                $customer->subject?->delete();
            }
            $customerGroup->delete();
            $customerGroup->subject?->delete();
        });
    }

    protected function syncSubject(CustomerGroup $customerGroup): void
    {
        $companyId = $customerGroup->company_id ?? getActiveCompany();

        $relation = 'subject';
        $parentId = config('amir.cust_subject');
        $subject = $customerGroup->$relation;

        if (! $subject) {
            $subject = $this->subjectService->createSubject([
                'name' => $customerGroup->name,
                'parent_id' => $parentId,
                'company_id' => $companyId,
            ]);
        }

        $needsSave = false;

        if ($subject->name !== $customerGroup->name) {
            $subject->name = $customerGroup->name;
            $needsSave = true;
        }

        if ($parentId && $subject->parent_id !== $parentId) {
            $subject->parent_id = $parentId;
            $needsSave = true;
        }

        if ($subject->subjectable_id !== $customerGroup->id || $subject->subjectable_type !== $customerGroup->getMorphClass()) {
            $subject->subjectable()->associate($customerGroup);
            $needsSave = true;
        }

        if ($needsSave) {
            $subject->save();
        }

        $customerGroup->setRelation($relation, $subject);

        if ($subject->id !== $customerGroup->subject_id) {
            $customerGroup->updateQuietly(['subject_id' => $subject->id]);
        }
    }
}
