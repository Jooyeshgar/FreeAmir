<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryDecree extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'org_chart_id',
        'name',
        'start_date',
        'end_date',
        'contract_type',
        'daily_wage',
        'description',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_wage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function orgChart(): BelongsTo
    {
        return $this->belongsTo(OrgChart::class, 'org_chart_id');
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(DecreeBenefit::class, 'decree_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'decree_id');
    }
}
