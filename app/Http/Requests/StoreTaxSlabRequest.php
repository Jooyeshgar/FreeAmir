<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxSlabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'income_to' => 'nullable|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
        ];
    }
}
