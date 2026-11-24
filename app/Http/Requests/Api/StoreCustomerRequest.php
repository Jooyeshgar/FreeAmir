<?php

namespace App\Http\Requests\Api;

use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(CustomerType::valueNames())],
            'group_id' => [
                'required',
                'integer',
                Rule::exists('customer_groups', 'id')
                    ->where('fiscal_year_id', getActiveFiscalYear()),
            ],
            'subject_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
            ],
            'mobile' => ['nullable', 'string', 'max:15'],
            'phone' => ['nullable', 'string', 'max:15'],
            'fax' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:64'],
            'address' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:15'],
            'personal_code' => ['nullable', 'string', 'max:15'],
            'ecnmcs_code' => ['nullable', 'string', 'max:20'],
            'web_page' => ['nullable', 'string', 'max:50'],
            'responsible' => ['nullable', 'string', 'max:50'],
            'connector' => ['nullable', 'string', 'max:50'],
            'desc' => ['nullable', 'string', 'max:150'],
            'rep_via_email' => ['sometimes', 'boolean'],
            'acc_name_1' => ['nullable', 'string', 'max:50'],
            'acc_no_1' => ['nullable', 'string', 'max:30'],
            'acc_bank_1' => ['nullable', 'string', 'max:50'],
            'acc_name_2' => ['nullable', 'string', 'max:50'],
            'acc_no_2' => ['nullable', 'string', 'max:30'],
            'acc_bank_2' => ['nullable', 'string', 'max:50'],
        ];
    }
}
