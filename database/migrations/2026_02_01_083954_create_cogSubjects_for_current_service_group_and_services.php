<?php

use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $subjectService = app(SubjectService::class);

            $serviceGroups = ServiceGroup::withoutGlobalScopes()->get();

            foreach ($serviceGroups as $serviceGroup) {
                $companyId = $serviceGroup->company_id;

                $parentId = config('amir.cogs_service');
                $subject = $serviceGroup->cogs_subject_id
                    ? Subject::withoutGlobalScopes()->find($serviceGroup->cogs_subject_id)
                    : null;

                if (! $subject) {
                    $subject = $subjectService->createSubject([
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

                $normalizedParentId = $parentId ?: null;
                if ($subject->parent_id !== $normalizedParentId) {
                    $subject->parent_id = $normalizedParentId;
                    $needsSave = true;
                }

                if ($subject->subjectable_id !== $serviceGroup->id || $subject->subjectable_type !== $serviceGroup->getMorphClass()) {
                    $subject->subjectable()->associate($serviceGroup);
                    $needsSave = true;
                }

                if ($needsSave) {
                    $subject->save();
                }

                if ($serviceGroup->cogs_subject_id !== $subject->id) {
                    $serviceGroup->updateQuietly(['cogs_subject_id' => $subject->id]);
                }
            }

            $services = Service::withoutGlobalScopes()->get();

            foreach ($services as $service) {
                $serviceGroup = $service->group
                    ? ServiceGroup::withoutGlobalScopes()->find($service->group)
                    : null;
                $companyId = $service->company_id ?? $serviceGroup?->company_id;

                if (! $companyId) {
                    throw new RuntimeException('Unable to determine company for service cogs subject creation.');
                }

                $parentId = $serviceGroup?->cogs_subject_id;
                $subject = $service->cogs_subject_id
                    ? Subject::withoutGlobalScopes()->find($service->cogs_subject_id)
                    : null;

                if (! $subject) {
                    $subject = $subjectService->createSubject([
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

                if ($service->cogs_subject_id !== $subject->id) {
                    $service->updateQuietly(['cogs_subject_id' => $subject->id]);
                }
            }
        });
    }
};
