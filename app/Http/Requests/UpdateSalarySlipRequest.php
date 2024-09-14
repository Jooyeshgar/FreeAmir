<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalarySlipRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Change to authorization logic if needed
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'daily_wage' => 'required|numeric',
            'hourly_overtime' => 'nullable|numeric',
            'holiday_work' => 'nullable|numeric',
            'friday_work' => 'nullable|numeric',
            'child_allowance' => 'nullable|numeric',
            'housing_allowance' => 'nullable|numeric',
            'food_allowance' => 'nullable|numeric',
            'marriage_allowance' => 'nullable|numeric',
            'payroll_pattern_id' => 'required|exists:payroll_patterns,id',
            'benefits_deductions' => 'nullable|array',
            'benefits_deductions.*' => 'exists:benefits_deductions,id',
            'description' => 'nullable|string',
        ];
    }
}
