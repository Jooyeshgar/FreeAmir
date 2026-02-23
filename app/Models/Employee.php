<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'father_name',
        'national_code',
        'passport_number',
        'nationality',
        'gender',
        'marital_status',
        'children_count',
        'birth_date',
        'birth_place',
        'duty_status',
        'phone',
        'address',
        'insurance_number',
        'insurance_type',
        'bank_name',
        'bank_account',
        'card_number',
        'shaba_number',
        'education_level',
        'field_of_study',
        'employment_type',
        'contract_start_date',
        'contract_end_date',
        'org_chart_id',
        'work_site_id',
        'contract_framework_id',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'is_active' => 'boolean',
        'children_count' => 'integer',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function orgChart(): BelongsTo
    {
        return $this->belongsTo(OrgChart::class, 'org_chart_id');
    }

    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class, 'work_site_id');
    }

    public function contractFramework(): BelongsTo
    {
        return $this->belongsTo(ContractFramework::class, 'contract_framework_id');
    }

    public function salaryDecrees(): HasMany
    {
        return $this->hasMany(SalaryDecree::class, 'employee_id');
    }

    public function monthlyAttendances(): HasMany
    {
        return $this->hasMany(MonthlyAttendance::class, 'employee_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'employee_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }

    public function personnelRequests(): HasMany
    {
        return $this->hasMany(PersonnelRequest::class, 'employee_id');
    }

    public function approvedPersonnelRequests(): HasMany
    {
        return $this->hasMany(PersonnelRequest::class, 'approved_by');
    }
}
