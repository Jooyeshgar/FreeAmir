<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use App\Services\SubjectCreatorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sstid',
        'buyId',
        'sellId',
        'vat',
        'company_id',
        'return_sales_subject_id',
        'income_subject_id',
        'cogs_subject_id',
        'inventory_subject_id',
    ];

    protected $attributes = [
        'vat' => 0,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($model) {
            $model->company_id ??= session('active-company-id');
        });

        static::created(function ($productGroup) {
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

            $productGroup->updateQuietly([
                'income_subject_id' => $incomeSubject->id,
                'cogs_subject_id' => $cogsSubject->id,
                'inventory_subject_id' => $inventorySubject->id,
                'return_sales_subject_id' => $return_salesSubject->id,
            ]);
        });

        static::updated(function ($productGroup) {
            $subjectCreator = app(SubjectCreatorService::class);

            // Update subjects
            $subjectCreator->editSubject($productGroup->inventorySubject, [
                'name' => $productGroup->name,
                'parent_id' => config('amir.product'),
                'company_id' => $productGroup->company_id,
            ]);
            $subjectCreator->editSubject($productGroup->cogsSubject, [
                'name' => $productGroup->name,
                'parent_id' => config('amir.cost_of_goods'),
                'company_id' => $productGroup->company_id,
            ]);
            $subjectCreator->editSubject($productGroup->returnSalesSubject, [
                'name' => $productGroup->name,
                'parent_id' => config('amir.return_sales'),
                'company_id' => $productGroup->company_id,
            ]);
            $subjectCreator->editSubject($productGroup->incomeSubject, [
                'name' => $productGroup->name,
                'parent_id' => config('amir.income'),
                'company_id' => $productGroup->company_id,
            ]);
        });

        static::deleted(function ($productGroup) {
            // Delete related productGroup
            $productGroup->incomeSubject?->delete();
            $productGroup->cogsSubject?->delete();
            $productGroup->inventorySubject?->delete();
            $productGroup->returnSalesSubject?->delete();
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'group', 'id');
    }

    public function incomeSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'income_subject_id');
    }

    public function returnSalesSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'return_sales_subject_id');
    }

    public function cogsSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'cogs_subject_id');
    }

    public function inventorySubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'inventory_subject_id');
    }
}
