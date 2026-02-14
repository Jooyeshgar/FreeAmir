<?php

namespace App\Services;

use App\Models\Service;

class ServiceService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): Service
    {
        $data['company_id'] ??= getActiveCompany();

        $service = Service::create($data);

        $this->syncSubjects($service);

        return $service;
    }

    public function update(Service $service, array $data): Service
    {
        $service->fill($data);
        $service->save();

        $this->syncSubjects($service);

        return $service;
    }

    public function delete(Service $service): void
    {
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
        $companyId = $service->company_id ?? $group?->company_id ?? getActiveCompany();

        if (! $companyId) {
            throw new \RuntimeException('Unable to determine company for service subject synchronization.');
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
