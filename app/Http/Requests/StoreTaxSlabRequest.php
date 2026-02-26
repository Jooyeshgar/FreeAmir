<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxSlabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:1300|max:1500',
            'slab_order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('tax_slabs')->where(fn ($q) => $q->where('year', $this->input('year'))),
            ],
            'income_from' => 'required|numeric|min:0',
            'income_to' => 'nullable|numeric|gt:income_from',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'annual_exemption' => 'nullable|numeric|min:0',
        ];
    }
}
