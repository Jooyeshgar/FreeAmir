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
        'sales_subject_id',
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

            // Create the three subjects
            $sales = $subjectCreator->createSubject([
                'name' => $product->name.' - Sales Revenue',
                'parent_id' => $parentGroup->subject_id ?? 0,
                'company_id' => session('active-company-id'),
            ]);
            $sales->subjectable()->associate($product);
            $sales->save();

            $cogs = $subjectCreator->createSubject([
                'name' => $product->name.' - Cost of Goods Sold',
                'parent_id' => $parentGroup->subject_id ?? 0,
                'company_id' => session('active-company-id'),
            ]);
            $cogs->subjectable()->associate($product);
            $cogs->save();

            $inventory = $subjectCreator->createSubject([
                'name' => $product->name.' - Inventory',
                'parent_id' => $parentGroup->subject_id ?? 0,
                'company_id' => session('active-company-id'),
            ]);
            $inventory->subjectable()->associate($product);
            $inventory->save();

            $product->update([
                'sales_subject_id' => $sales->id,
                'cogs_subject_id' => $cogs->id,
                'inventory_subject_id' => $inventory->id,
            ]);
        });

        static::deleted(function ($product) {
            // Delete related subjects
            $product->salesSubject?->delete();
            $product->cogsSubject?->delete();
            $product->inventorySubject?->delete();
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

    public function salesSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'sales_subject_id');
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
