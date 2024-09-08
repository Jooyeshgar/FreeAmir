<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'daily_wage',
        'hourly_overtime',
        'holiday_work',
        'friday_work',
        'child_allowance',
        'housing_allowance',
        'food_allowance',
        'marriage_allowance',
        'payroll_pattern_id',
        'description',
    ];

    // Many-to-many relationship with BenefitsDeduction
    public function benefitsDeductions()
    {
        return $this->belongsToMany(BenefitsDeduction::class, 'salary_slip_benefit_deduction', 'salary_slip_id', 'benefits_deductions_id')
            ->withPivot('amount') // Include the amount field in the pivot table
            ->withTimestamps();
    }

    // Relationship to SalaryPattern (one-to-many)
    public function payrollPattern()
    {
        return $this->belongsTo(PayrollPattern::class);
    }
}
