<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use App\Services\SubjectCreatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
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
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());

        static::creating(function ($product) {
            $product->company_id ??= session('active-company-id');
        });

        static::created(function ($product) {
            $parentGroup = $product->productGroup;
            $subject = app(SubjectCreatorService::class)->createSubject([
                'name' => $product->name,
                'parent_id' => $parentGroup->subject_id ?? 0,
                'company_id' => session('active-company-id'),
            ]);
            $subject = $product->subject()->save($subject);

            $product->update(['subject_id' => $subject->id]);
        });

        static::deleting(function ($product) {
            // Delete the related subject when the product is deleted
            if ($product->subject) {
                $product->subject->delete();
            }
        });
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group');
    }

    public function subject()
    {
        return $this->morphOne(Subject::class, 'subjectable');
    }
}
