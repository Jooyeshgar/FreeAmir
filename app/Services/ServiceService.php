<?php

namespace App\Services;

use App\Models\Service;

class ServiceService
{
    public function __construct(private readonly SubjectCreatorService $subjectCreator) {}

    public function create(array $data): Service
    {
        $data['company_id'] ??= session('active-company-id');

        $service = Service::create($data);

        $this->syncSubject($service);

        return $service;
    }

    public function update(Service $service, array $data): Service
    {
        $service->fill($data);
        $service->save();

        $this->syncSubject($service);

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
    }

    protected function syncSubject(Service $service): void
    {
        $group = $service->serviceGroup;
        $companyId = $service->company_id ?? $group?->company_id ?? session('active-company-id');

        if (! $companyId) {
            throw new \RuntimeException('Unable to determine company for service subject synchronization.');
        }

        $column = 'subject_id';
        $relation = 'subject';

        $parentId = $group->subject_id;
        $subject = $service->$relation;

        if (! $subject) {
            $subject = $this->subjectCreator->createSubject([
                'name' => $service->name,
                'parent_id' => $parentId,
                'company_id' => $companyId,
            ]);
        }

        $needsSave = false;

        if ($subject->name !== $service->name) {
            $subject->name = $service->name;
            $needsSave = true;
        }

        if ($parentId && $subject->parent_id !== $parentId) {
            $subject->parent_id = $parentId;
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

        if ($subject->id !== $service->$column) {
            $service->updateQuietly([$column => $subject->id]);
        }
    }
}
