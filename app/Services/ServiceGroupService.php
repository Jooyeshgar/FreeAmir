<?php

namespace App\Services;

use App\Models\ServiceGroup;

class ServiceGroupService
{
    public function __construct(private readonly SubjectCreatorService $subjectCreator) {}

    public function create(array $data): ServiceGroup
    {
        $serviceGroup = ServiceGroup::create($data);

        $this->syncSubjects($serviceGroup);

        return $serviceGroup;
    }

    public function update(ServiceGroup $serviceGroup, array $data): ServiceGroup
    {
        $serviceGroup->fill($data);
        $serviceGroup->save();

        $this->syncSubjects($serviceGroup);

        return $serviceGroup;
    }

    public function delete(ServiceGroup $serviceGroup): void
    {
        $this->deleteSubjects($serviceGroup);

        $serviceGroup->delete();
    }

    public function deleteSubjects(ServiceGroup $serviceGroup): void
    {
        $serviceGroup->subject?->delete();
    }

    protected function syncSubjects(ServiceGroup $serviceGroup): void
    {
        $companyId = $serviceGroup->company_id ?? session('active-company-id');

        $column = 'subject_id';
        $relation = 'incomeSubject';
        $configKey = 'amir.sales_revenue';

        $parentId = config($configKey);
        $subject = $serviceGroup->$relation;

        if (! $subject) {
            $subject = $this->subjectCreator->createSubject([
                'name' => $serviceGroup->name,
                'parent_id' => $parentId,
                'company_id' => $companyId,
            ]);
        }

        $needsSave = false;

        if ($subject->name !== $serviceGroup->name) {
            $subject->name = $serviceGroup->name;
            $needsSave = true;
        }

        if ($parentId && $subject->parent_id !== $parentId) {
            $subject->parent_id = $parentId;
            $needsSave = true;
        }

        if ($subject->subjectable_id !== $serviceGroup->id || $subject->subjectable_type !== $serviceGroup->getMorphClass()) {
            $subject->subjectable()->associate($serviceGroup);
            $needsSave = true;
        }

        if ($needsSave) {
            $subject->save();
        }

        $serviceGroup->setRelation($relation, $subject);

        if ($subject->id !== $serviceGroup->$column) {
            $serviceGroup->updateQuietly([$column => $subject->id]);
        }
    }
}
