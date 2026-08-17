<?php

namespace App\Services;

use App\Models\ProductGroup;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductGroupService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function create(array $data): ProductGroup
    {
        $productGroup = ProductGroup::create($data);

        $this->syncSubjects($productGroup);

        return $productGroup;
    }

    public function update(ProductGroup $productGroup, array $data): ProductGroup
    {
        $productGroup->fill($data);
        $productGroup->save();

        $this->syncSubjects($productGroup);

        return $productGroup;
    }

    public function delete(ProductGroup $productGroup): void
    {
        DB::transaction(function () use ($productGroup): void {
            $this->ensureCanDelete($productGroup);

            $productGroup->delete();

            $this->deleteSubjects($productGroup);
        });
    }

    public function ensureCanDelete(ProductGroup $productGroup): void
    {
        $message = $this->deleteBlockingReason($productGroup);

        if ($message) {
            throw ValidationException::withMessages(['product_group' => $message]);
        }
    }

    public function deleteBlockingReason(ProductGroup $productGroup): ?string
    {
        $productGroup->loadMissing('incomeSubject', 'salesReturnsSubject', 'cogsSubject', 'inventorySubject');

        if ($productGroup->products()->exists()) {
            return __('Cannot delete product group because it has products.');
        }

        return $this->subjectsBlockingReason([
            $productGroup->incomeSubject,
            $productGroup->salesReturnsSubject,
            $productGroup->cogsSubject,
            $productGroup->inventorySubject,
        ], $productGroup);
    }

    public function deleteSubjects(ProductGroup $productGroup): void
    {
        $productGroup->incomeSubject?->delete();
        $productGroup->salesReturnsSubject?->delete();
        $productGroup->cogsSubject?->delete();
        $productGroup->inventorySubject?->delete();
    }

    protected function syncSubjects(ProductGroup $productGroup): void
    {
        $companyId = $productGroup->company_id ?? getActiveCompany();

        $subjectsConfig = [
            'income_subject_id' => [
                'relation' => 'incomeSubject',
                'config_key' => 'amir.sales_revenue',
            ],
            'sales_returns_subject_id' => [
                'relation' => 'salesReturnsSubject',
                'config_key' => 'amir.sales_returns',
            ],
            'cogs_subject_id' => [
                'relation' => 'cogsSubject',
                'config_key' => 'amir.cost_of_goods_sold',
            ],
            'inventory_subject_id' => [
                'relation' => 'inventorySubject',
                'config_key' => 'amir.inventory',
            ],
        ];

        $updatedIds = [];

        foreach ($subjectsConfig as $column => $settings) {
            $relation = $settings['relation'];
            $parentId = config($settings['config_key']);
            $subject = $productGroup->$relation;

            if (! $subject) {
                $subject = $this->subjectService->createSubject([
                    'name' => $productGroup->name,
                    'parent_id' => $parentId,
                    'company_id' => $companyId,
                ]);
            }

            $needsSave = false;

            if ($subject->name !== $productGroup->name) {
                $subject->name = $productGroup->name;
                $needsSave = true;
            }

            if ($parentId && $subject->parent_id !== $parentId) {
                $subject->parent_id = $parentId;
                $needsSave = true;
            }

            if ($subject->subjectable_id !== $productGroup->id || $subject->subjectable_type !== $productGroup->getMorphClass()) {
                $subject->subjectable()->associate($productGroup);
                $needsSave = true;
            }

            if ($needsSave) {
                $subject->save();
            }

            $productGroup->setRelation($relation, $subject);
            $updatedIds[$column] = $subject->id;
        }

        $dirtyIds = [];

        foreach ($updatedIds as $column => $id) {
            if ($id !== $productGroup->$column) {
                $dirtyIds[$column] = $id;
            }
        }

        if ($dirtyIds) {
            $productGroup->update($dirtyIds);
        }
    }

    private function subjectsBlockingReason(array $subjects, ProductGroup $productGroup): ?string
    {
        foreach (array_filter($subjects) as $subject) {
            if ($subject->children()->exists()) {
                return __('Cannot delete product group because one of its subjects has children.');
            }

            if ($subject->transactions()->exists()) {
                return __('Cannot delete product group because one of its subjects has transactions.');
            }

            if ($this->subjectHasExternalRelation($subject, $productGroup)) {
                return __('Cannot delete product group because one of its subjects has another relationship.');
            }
        }

        return null;
    }

    private function subjectHasExternalRelation(Subject $subject, ProductGroup $productGroup): bool
    {
        if (! $subject->subjectable_type || ! $subject->subjectable_id) {
            return false;
        }

        if (
            $subject->subjectable_type === $productGroup->getMorphClass()
            && (int) $subject->subjectable_id === (int) $productGroup->id
        ) {
            return false;
        }

        return $subject->subjectable()->exists();
    }
}
