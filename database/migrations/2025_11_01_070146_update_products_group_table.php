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

            $return_salesSubject = $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.return_sales'),
                'company_id' => $productGroup->company_id,
            ]);
            $return_salesSubject->subjectable()->associate($productGroup);
            $return_salesSubject->save();

            $cogsSubject = $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.cost_of_goods'),
                'company_id' => $productGroup->company_id,
            ]);
            $cogsSubject->subjectable()->associate($productGroup);
            $cogsSubject->save();

            $inventorySubject = $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.product'),
                'company_id' => $productGroup->company_id,
            ]);
            $inventorySubject->subjectable()->associate($productGroup);
            $inventorySubject->save();

            $productGroup->update([
                'income_subject_id' => $incomeSubject->id,
                'cogs_subject_id' => $cogsSubject->id,
                'inventory_subject_id' => $inventorySubject->id,
                'return_sales_subject_id' => $return_salesSubject->id,
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
            $productGroup->returnSalesSubject?->delete();

            $subjectCreator->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.product') ?? 0,
                'company_id' => $productGroup->company_id,
            ]);
        }
    }
};
