<?php

namespace App\Services;

use App\Models\ServiceGroup;

class ServiceGroupService
{
    public function __construct(private readonly SubjectService $subjectService) {}

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
        $serviceGroup->delete();

        $this->deleteSubjects($serviceGroup);
    }

    public function deleteSubjects(ServiceGroup $serviceGroup): void
    {
        $serviceGroup->subject?->delete();
        $serviceGroup->cogsSubject?->delete();
    }

    protected function syncSubjects(ServiceGroup $serviceGroup): void
    {
        $serviceGroup->loadMissing('subject', 'cogsSubject');

        $companyId = $serviceGroup->company_id ?? getActiveCompany();

        $subjectsConfig = [
            'subject_id' => [
                'relation' => 'subject',
                'config_key' => 'amir.service_revenue',
            ],
            'cogs_subject_id' => [
                'relation' => 'cogsSubject',
                'config_key' => 'amir.cogs_service',
            ],
        ];

        $updatedIds = [];

        foreach ($subjectsConfig as $column => $settings) {
            $relation = $settings['relation'];
            $parentId = config($settings['config_key']);
            $subject = $serviceGroup->$relation;

            if (! $subject) {
                $subject = $this->subjectService->createSubject([
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
            $updatedIds[$column] = $subject->id;
        }

        $dirtyIds = [];

        foreach ($updatedIds as $column => $id) {
            if ($id !== $serviceGroup->$column) {
                $dirtyIds[$column] = $id;
            }
        }

        if ($dirtyIds) {
            $serviceGroup->updateQuietly($dirtyIds);
        }
    }
}
