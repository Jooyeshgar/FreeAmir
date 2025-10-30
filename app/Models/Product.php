<?php

namespace App\Models;

use App\Enums\ConfigTitle;
use App\Models\Scopes\FiscalYearScope;
use App\Services\SubjectCreatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'sstid',
        'group',
        'location',
        'quantity',
        'quantity_warning',
        'oversell',
        'purchace_price',
        'selling_price',
        'discount_formula',
        'description',
        'company_id',
        'return_sales_subject_id',
        'income_subject_id',
        'cogs_subject_id',
        'inventory_subject_id',
        'vat',
        'average_cost',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($product) {
            $product->company_id ??= session('active-company-id');
        });

        static::created(function ($product) {
            $parentGroup = $product->productGroup;
            $subjectCreator = app(SubjectCreatorService::class);

            // Create the four subjects
            $incomeSubject = $subjectCreator->createSubject([
                'name' => $product->name.' '.ConfigTitle::INCOME->value,
                'parent_id' => $parentGroup->income_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $incomeSubject->subjectable()->associate($product);

            $return_salesSubject = $subjectCreator->createSubject([
                'name' => $product->name.' '.ConfigTitle::RETURN_SALES->value,
                'parent_id' => $parentGroup->return_sales_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $return_salesSubject->subjectable()->associate($product);

            $cogsSubject = $subjectCreator->createSubject([
                'name' => $product->name.' '.ConfigTitle::COST_OF_GOODS->value,
                'parent_id' => $parentGroup->cogs_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $cogsSubject->subjectable()->associate($product);

            $inventorySubject = $subjectCreator->createSubject(data: [
                'name' => $product->name.' '.ConfigTitle::PRODUCT->value,
                'parent_id' => $parentGroup->inventory_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $inventorySubject->subjectable()->associate($product);

            $product->update([
                'income_subject_id' => $incomeSubject->id,
                'cogs_subject_id' => $cogsSubject->id,
                'inventory_subject_id' => $inventorySubject->id,
                'return_sales_subject_id' => $return_salesSubject->id,
            ]);
        });

        static::updated(function ($product) {
            $parentGroup = $product->productGroup;
            $subjectCreator = app(SubjectCreatorService::class);

            if (! $product->incomeSubject) {
                // Create the four subjects
                $incomeSubject = $subjectCreator->createSubject([
                    'name' => $product->name.' '.ConfigTitle::INCOME->value,
                    'parent_id' => $parentGroup->income_subject_id ?? 0,
                    'company_id' => $parentGroup->company_id,
                ]);
                $incomeSubject->subjectable()->associate($product);
                $incomeSubject->save();

                $return_salesSubject = $subjectCreator->createSubject([
                    'name' => $product->name.' '.ConfigTitle::RETURN_SALES->value,
                    'parent_id' => $parentGroup->return_sales_subject_id ?? 0,
                    'company_id' => $parentGroup->company_id,
                ]);
                $return_salesSubject->subjectable()->associate($product);
                $return_salesSubject->save();

                $cogsSubject = $subjectCreator->createSubject([
                    'name' => $product->name.' '.ConfigTitle::COST_OF_GOODS->value,
                    'parent_id' => $parentGroup->cogs_subject_id ?? 0,
                    'company_id' => $parentGroup->company_id,
                ]);
                $cogsSubject->subjectable()->associate($product);
                $cogsSubject->save();

                $inventorySubject = $subjectCreator->createSubject([
                    'name' => $product->name.' '.ConfigTitle::PRODUCT->value,
                    'parent_id' => $parentGroup->inventory_subject_id ?? 0,
                    'company_id' => $parentGroup->company_id,
                ]);
                $inventorySubject->subjectable()->associate($product);
                $inventorySubject->save();

                $product->updateQuietly([
                    'income_subject_id' => $incomeSubject->id,
                    'cogs_subject_id' => $cogsSubject->id,
                    'inventory_subject_id' => $inventorySubject->id,
                    'return_sales_subject_id' => $return_salesSubject->id,
                ]);
            }
        });

        static::deleted(function ($product) {
            // Delete related subjects
            $product->incomeSubject?->delete();
            $product->cogsSubject?->delete();
            $product->inventorySubject?->delete();
            $product->returnSalesSubject?->delete();
        });
    }

    // Relationships
    public function productWebsites(): HasMany
    {
        return $this->hasMany(ProductWebsite::class, 'product_id');
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group');
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
