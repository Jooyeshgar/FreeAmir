<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecreeBenefit extends Model
{
    use HasFactory;

    protected $fillable = [
        'decree_id',
        'element_id',
        'element_value',
    ];

    protected $casts = [
        'element_value' => 'decimal:2',
    ];

    public function decree(): BelongsTo
    {
        return $this->belongsTo(SalaryDecree::class, 'decree_id');
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(PayrollElement::class, 'element_id');
    }
}
