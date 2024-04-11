<?php

namespace App\Models;


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
        'description'
    ];


    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group');
    }

    // Define any other methods as needed
}
