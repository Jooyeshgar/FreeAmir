<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rep_via_email' => $this->has('rep_via_email'),
        ]);
    }

    public function rules(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');
        $customerId = $customer?->id;

        return [
            'name' => [
                'required',
                'max:100',
                'string',
                'regex:/^[\w\d\s]*$/u',
                Rule::unique('customers', 'name')
                    ->where('group_id', $this->input('group_id'))
                    ->ignore($customerId),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:15',
                'regex:/^\d+$/',
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],
            'cell' => [
                'nullable',
                'string',
                'max:15',
                'regex:/^09\d{9}$/',
            ],
            'fax' => 'nullable|string',
            'address' => 'nullable|max:100|string|regex:/^[\w\d\s]*$/u',
            'postal_code' => 'nullable|string|max:15',
            'email' => 'nullable|email|max:64',
            'ecnmcs_code' => 'nullable|string|max:20',
            'personal_code' => 'nullable|string|max:15',
            'web_page' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'responsible' => 'nullable|string|max:50',
            'connector' => 'nullable|string|max:50',
            'group_id' => 'required|exists:customer_groups,id|integer',
            'desc' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'rep_via_email' => 'boolean',
            'acc_name_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_1' => 'nullable|string|max:30|regex:/^[\w\d\s]*$/u',
            'acc_bank_1' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_name_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
            'acc_no_2' => 'nullable|string|max:30|regex:/^[\w\d\s]*$/u',
            'acc_bank_2' => 'nullable|string|max:50|regex:/^[\w\d\s]*$/u',
        ];
    }
}
