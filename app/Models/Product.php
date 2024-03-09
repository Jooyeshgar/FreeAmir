<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
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
    ];

    // Define a relationship with the product group (if applicable)

    public function group()
    {
        return $this->belongsTo(ProductGroup::class, 'group');
    }

    // Define any other methods as needed
}
