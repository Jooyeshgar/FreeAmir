<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenefitsDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'calculation',
        'insurance_included',
        'tax_included',
        'show_on_payslip',
        'amount',
    ];
    public function salarySlips()
    {
        return $this->belongsToMany(SalarySlip::class, 'salary_slip_benefit_deduction', 'benefits_deductions_id', 'salary_slip_id')
            ->withPivot('amount') // Include the amount field in the pivot table
            ->withTimestamps();
    }
}
