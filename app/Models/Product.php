<?php

namespace App\Models;

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
        'subject_id',
        'vat',
        'average_cost',
        'income_subject_id',
        'cogs_subject_id',
        'inventory_subject_id',
        'sales_returns_subject_id',
    ];

    public static function booted(): void
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
                'name' => $product->name,
                'parent_id' => $parentGroup->income_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $incomeSubject->subjectable()->associate($product);
            $incomeSubject->save();

            $sales_returnsSubject = $subjectCreator->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->sales_returns_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $sales_returnsSubject->subjectable()->associate($product);
            $sales_returnsSubject->save();

            $cogsSubject = $subjectCreator->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->cogs_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $cogsSubject->subjectable()->associate($product);
            $cogsSubject->save();

            $inventorySubject = $subjectCreator->createSubject(data: [
                'name' => $product->name,
                'parent_id' => $parentGroup->inventory_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $inventorySubject->subjectable()->associate($product);
            $inventorySubject->save();

            $product->updateQuietly([
                'income_subject_id' => $incomeSubject->id,
                'cogs_subject_id' => $cogsSubject->id,
                'inventory_subject_id' => $inventorySubject->id,
                'sales_returns_subject_id' => $sales_returnsSubject->id,
            ]);
        });

        static::updated(function ($product) {
            $parentGroup = $product->productGroup;
            $subjectCreator = app(SubjectCreatorService::class);

            // Update subjects
            $subjectCreator->editSubject($product->cogsSubject, [
                'name' => $product->name,
                'parent_id' => $parentGroup->cogs_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $subjectCreator->editSubject($product->inventorySubject, [
                'name' => $product->name,
                'parent_id' => $parentGroup->inventory_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $subjectCreator->editSubject($product->salesReturnsSubject, [
                'name' => $product->name,
                'parent_id' => $parentGroup->sales_returns_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
            $subjectCreator->editSubject($product->incomeSubject, [
                'name' => $product->name,
                'parent_id' => $parentGroup->income_subject_id ?? 0,
                'company_id' => $parentGroup->company_id,
            ]);
        });

        static::deleted(function ($product) {
            // Delete the related subjects when the product is deleted
            $product->incomeSubject?->delete();
            $product->cogsSubject?->delete();
            $product->inventorySubject?->delete();
            $product->salesReturnsSubject?->delete();
        });
    }

    public function productWebsites(): HasMany
    {
        return $this->hasMany(ProductWebsite::class, 'product_id');
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'product_id');
    }

    public function incomeSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'income_subject_id');
    }

    public function salesReturnsSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'sales_returns_subject_id');
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
