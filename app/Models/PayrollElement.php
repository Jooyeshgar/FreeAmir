<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'system_code',
        'category',
        'calc_type',
        'formula',
        'default_amount',
        'is_taxable',
        'is_insurable',
        'show_in_payslip',
        'is_system_locked',
        'gl_account_code',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_insurable' => 'boolean',
        'show_in_payslip' => 'boolean',
        'is_system_locked' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function decreebenefits(): HasMany
    {
        return $this->hasMany(DecreeBenefit::class, 'element_id');
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'element_id');
    }
}
