<?php

namespace App\Services;

use App\Models\ServiceGroup;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        DB::transaction(function () use ($serviceGroup): void {
            $this->ensureCanDelete($serviceGroup);

            $serviceGroup->delete();

            $this->deleteSubjects($serviceGroup);
        });
    }

    public function ensureCanDelete(ServiceGroup $serviceGroup): void
    {
        $message = $this->deleteBlockingReason($serviceGroup);

        if ($message) {
            throw ValidationException::withMessages(['service_group' => $message]);
        }
    }

    public function deleteBlockingReason(ServiceGroup $serviceGroup): ?string
    {
        $serviceGroup->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');

        if ($serviceGroup->services()->exists()) {
            return __('Cannot delete service group because it has services.');
        }

        return $this->subjectsBlockingReason([
            $serviceGroup->subject,
            $serviceGroup->cogsSubject,
            $serviceGroup->salesReturnsSubject,
        ], $serviceGroup);
    }

    public function deleteSubjects(ServiceGroup $serviceGroup): void
    {
        $serviceGroup->subject?->delete();
        $serviceGroup->cogsSubject?->delete();
        $serviceGroup->salesReturnsSubject?->delete();
    }

    protected function syncSubjects(ServiceGroup $serviceGroup): void
    {
        $serviceGroup->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');

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
            'sales_returns_subject_id' => [
                'relation' => 'salesReturnsSubject',
                'config_key' => 'amir.sales_returns',
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
            $serviceGroup->update($dirtyIds);
        }
    }

    private function subjectsBlockingReason(array $subjects, ServiceGroup $serviceGroup): ?string
    {
        foreach (array_filter($subjects) as $subject) {
            if ($subject->children()->exists()) {
                return __('Cannot delete service group because one of its subjects has children.');
            }

            if ($subject->transactions()->exists()) {
                return __('Cannot delete service group because one of its subjects has transactions.');
            }

            if ($this->subjectHasExternalRelation($subject, $serviceGroup)) {
                return __('Cannot delete service group because one of its subjects has another relationship.');
            }
        }

        return null;
    }

    private function subjectHasExternalRelation(Subject $subject, ServiceGroup $serviceGroup): bool
    {
        if (! $subject->subjectable_type || ! $subject->subjectable_id) {
            return false;
        }

        if (
            $subject->subjectable_type === $serviceGroup->getMorphClass()
            && (int) $subject->subjectable_id === (int) $serviceGroup->id
        ) {
            return false;
        }

        return $subject->subjectable()->exists();
    }
}
