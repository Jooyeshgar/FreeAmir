<?php

use App\Services\SubjectCreatorService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $productsGroup = \App\Models\ProductGroup::all();
        foreach ($productsGroup as $productGroup) {
            $subjectCreator = app(SubjectCreatorService::class);
            $productGroup->company_id = $productGroup->company_id ?? session('active-company-id');

            $incomeSubject = $subjectCreator->createSubject(data: [
                'name' => $productGroup->name,
                'parent_id' => config('amir.income'),
                'company_id' => $productGroup->company_id,
            ]);
            $incomeSubject->subjectable()->associate($productGroup);
            $incomeSubject->save();

            $salesReturnsSubject = $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.sales_returns'),
                'company_id' => $productGroup->company_id,
            ]);
            $salesReturnsSubject->subjectable()->associate($productGroup);
            $salesReturnsSubject->save();

            $cogsSubject = $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.cost_of_goods_sold'),
                'company_id' => $productGroup->company_id,
            ]);
            $cogsSubject->subjectable()->associate($productGroup);
            $cogsSubject->save();

            $inventorySubject = $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.inventory'),
                'company_id' => $productGroup->company_id,
            ]);
            $inventorySubject->subjectable()->associate($productGroup);
            $inventorySubject->save();

            $productGroup->update([
                'income_subject_id' => $incomeSubject->id,
                'cogs_subject_id' => $cogsSubject->id,
                'inventory_subject_id' => $inventorySubject->id,
                'sales_returns_subject_id' => $salesReturnsSubject->id,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $productsGroup = \App\Models\ProductGroup::all();
        foreach ($productsGroup as $productGroup) {
            $subjectCreator = app(SubjectCreatorService::class);

            $productGroup->incomeSubject?->delete();
            $productGroup->cogsSubject?->delete();
            $productGroup->inventorySubject?->delete();
            $productGroup->salesReturnsSubject?->delete();

            $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.inventory') ?? 0,
                'company_id' => $productGroup->company_id,
            ]);
        }
    }
};
