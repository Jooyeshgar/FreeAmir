<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'sstid' => ['nullable', 'string', 'max:100'],
            'group' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('product_groups', 'id')->where('fiscal_year_id', getActiveFiscalYear()),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'quantity_warning' => ['nullable', 'numeric', 'min:0'],
            'oversell' => ['nullable', 'boolean'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount_formula' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'group.exists' => __('The selected product group was not found in the current fiscal year.'),
        ];
    }
}
