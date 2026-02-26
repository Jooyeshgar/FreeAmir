<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'decree_id',
        'year',
        'month',
        'total_earnings',
        'total_deductions',
        'net_payment',
        'employer_insurance',
        'issue_date',
        'status',
        'accounting_voucher_id',
        'description',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_payment' => 'decimal:2',
        'employer_insurance' => 'decimal:2',
        'issue_date' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function decree(): BelongsTo
    {
        return $this->belongsTo(SalaryDecree::class, 'decree_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'payroll_id');
    }

    public function personnelRequests(): HasMany
    {
        return $this->hasMany(PersonnelRequest::class, 'payroll_id');
    }
}
