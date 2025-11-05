<?php

namespace App\Services;

use App\Models\ProductGroup;

class ProductGroupService
{
    public function __construct(private readonly SubjectCreatorService $subjectCreator) {}

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
        $this->deleteSubjects($productGroup);

        $productGroup->delete();
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
        $companyId = $productGroup->company_id ?? session('active-company-id');

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
                $subject = $this->subjectCreator->createSubject([
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
            $productGroup->updateQuietly($dirtyIds);
        }
    }
}
