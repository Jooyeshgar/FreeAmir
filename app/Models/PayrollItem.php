<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'element_id',
        'calculated_amount',
        'unit_count',
        'unit_rate',
        'description',
    ];

    protected $casts = [
        'calculated_amount' => 'decimal:2',
        'unit_count' => 'decimal:2',
        'unit_rate' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(PayrollElement::class, 'element_id');
    }
}
