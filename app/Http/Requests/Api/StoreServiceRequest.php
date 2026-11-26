<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
                Rule::exists('service_groups', 'id')->where('fiscal_year_id', getActiveFiscalYear()),
            ],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:150'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'group.exists' => __('The selected service group was not found in the current fiscal year.'),
        ];
    }
}
