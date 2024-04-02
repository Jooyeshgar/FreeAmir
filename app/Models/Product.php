<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
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
        'description'
    ];

    // Define a relationship with the product group (if applicable)

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group');
    }

    // Define any other methods as needed
}
