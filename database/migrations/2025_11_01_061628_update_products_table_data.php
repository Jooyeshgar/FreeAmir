<?php

use App\Services\SubjectCreatorService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations for Updatting existing products' subjects'
     */
    public function up(): void
    {
        $products = \App\Models\Product::all();
        foreach ($products as $product) {
            $parentGroup = $product->productGroup;
            $subjectCreator = app(SubjectCreatorService::class);

            // Create the four subjects
            $incomeSubject = $subjectCreator->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->income_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $incomeSubject->subjectable()->associate($product);
            $incomeSubject->save();

            $salesReturnsSubject = $subjectCreator->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->salesReturnsSubject ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $salesReturnsSubject->subjectable()->associate($product);
            $salesReturnsSubject->save();

            $cogsSubject = $subjectCreator->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->cogs_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $cogsSubject->subjectable()->associate($product);
            $cogsSubject->save();

            $inventorySubject = $subjectCreator->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->inventory_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $inventorySubject->subjectable()->associate($product);
            $inventorySubject->save();

            $product->update([
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
        $products = \App\Models\Product::all();
        foreach ($products as $product) {
            $parentGroup = $product->productGroup;
            $subjectCreator = app(SubjectCreatorService::class);
            $product->incomeSubject?->delete();
            $product->cogsSubject?->delete();
            $product->inventorySubject?->delete();
            $product->salesReturnsSubject?->delete();
            $subjectCreator->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
        }
    }
};
