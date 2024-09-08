<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollPattern extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'daily_wage',
        'overtime_hourly',
        'holiday_work',
        'friday_work',
        'child_allowance',
        'housing_allowance',
        'grocery_allowance',
        'marriage_allowance',
        'insurance_percentage',
        'unemployment_insurance',
        'employer_share'
    ];
}
