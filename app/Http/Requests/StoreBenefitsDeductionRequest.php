<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBenefitsDeductionRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Change to authorization logic if needed
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:benefit,deduction',
            'calculation' => 'required|in:fixed,hourly,manual',
            'amount' => 'required|numeric',
        ];
    }
}
