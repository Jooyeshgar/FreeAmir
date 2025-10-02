<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Adjust permission if you have a policy/ability; keep permissive for now
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize top-level scalars
        $this->merge([
            'date' => convertToGregorian($this->input('date')),
            'invoice_number' => convertToInt($this->input('invoice_number')),
            'document_number' => convertToInt($this->input('document_number')),
            'cash_payment' => convertToFloat($this->input('cash_payment', 0)),
            'additions' => convertToFloat($this->input('additions', 0)),
            'subtractions' => convertToFloat($this->input('subtractions', 0)),
        ]);
        $customer = Subject::find($this->input('customer_id'))->subjectable()->first();
        $this->merge(['customer_id' => $customer->id]);

        // 0 for buy, 1 for sell
        if ($this->input('invoice_type') == 'buy')
            $this->merge(['invoice_type' => 0]);
        else
            $this->merge(['invoice_type' => 1]);

        // Cast invoice_type (0/1 string) to integer boolean-like
        // if ($this->has('invoice_type')) {
        //     $type = $this->input('invoice_type');
        //     $this->merge(['invoice_type' => is_bool($type) ? (int) $type : (int) convertToInt($type)]);
        // }

        // Normalize transactions numeric fields and ids
        if ($this->has('transactions') && is_array($this->input('transactions'))) {
            $transactions = collect($this->input('transactions'))
                ->map(function ($t) {
                    return [
                        'transaction_id' => isset($t['transaction_id']) ? (int) $t['transaction_id'] : null,
                        'code' => $t['code'] ?? null,
                        'subject_id' => isset($t['subject_id']) ? (int) $t['subject_id'] : null,
                        'desc' => $t['desc'] ?? null,
                        'quantity' => isset($t['quantity']) ? convertToFloat($t['quantity']) : null,
                        'unit_discount' => isset($t['off']) ? convertToFloat($t['off']) : 0,
                        'unit' => isset($t['unit']) ? convertToFloat($t['unit']) : null,
                        'total' => isset($t['total']) ? convertToFloat($t['total']) : null,
                    ];
                })
                ->toArray();
            $this->merge(['transactions' => $transactions]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|min:2|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',

            // Invoice basics
            'invoice_type' => ['required', Rule::in([0, 1])],
            'customer_id' => 'required|exists:customers,id|integer',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'document_number' => [
                'required',
                'integer',
                Rule::unique('documents', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', session('active-company-id'));
                    }),
            ],
            'invoice_number' => [
                'required',
                'integer',
                Rule::unique('invoices', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', session('active-company-id'));
                    }),
            ],

            // Money-ish optional fields
            'cash_payment' => 'nullable|numeric|min:0',
            'additions' => 'nullable|numeric|min:0',
            'subtractions' => 'nullable|numeric|min:0',

            // Transactions array
            'transactions' => 'required|array|min:1',
            'transactions.*.subject_id' => 'required|integer|exists:subjects,id',
            'transactions.*.desc' => 'nullable|string|max:500',
            'transactions.*.quantity' => 'required|numeric|min:1',
            'transactions.*.unit_discount' => 'required|numeric|min:0',
        ];
    }

    /**
     * Custom validation messages for invoice rules.
     */
    public function messages(): array
    {
        return [
            // General fields
            'title.required' => __('The Title field is required.'),
            'title.string' => __('The Title field must be a valid string.'),
            'title.min' => __('The Title must be at least :min characters.'),
            'title.max' => __('The Title must not be greater than :max characters.'),

            'description.string' => __('The Description field must be a valid string.'),

            'date.required' => __('The Date field is required.'),
            'date.date' => __('The Date field must be a valid date.'),

            // Basics
            'invoice_type.required' => __('Please select the invoice type.'),
            'invoice_type.in' => __('The selected invoice type is invalid.'),

            'customer_id.required' => __('Please select the customer.'),
            'customer_id.exists' => __('The selected customer is invalid.'),
            'customer_id.integer' => __('The customer field must be an integer.'),

            'invoice_id.integer' => __('The invoice ID field must be an integer.'),
            'invoice_id.exists' => __('The selected invoice ID is invalid.'),

            'document_number.required' => __('The document number field is required.'),
            'document_number.integer' => __('The document number field must be an integer.'),
            'document_number.unique' => __('This document number has already been used for this company.'),

            'invoice_number.required' => __('The invoice number field is required.'),
            'invoice_number.integer' => __('The invoice number field must be an integer.'),
            'invoice_number.unique' => __('This invoice number has already been used for this company.'),

            // Money-ish
            'cash_payment.numeric' => __('The cash payment must be a number.'),
            'cash_payment.min' => __('The cash payment may not be negative.'),
            'additions.numeric' => __('The additions must be a number.'),
            'additions.min' => __('The additions may not be negative.'),
            'subtractions.numeric' => __('The subtractions must be a number.'),
            'subtractions.min' => __('The subtractions may not be negative.'),

            // Transactions
            'transactions.required' => __('At least one transaction row is required.'),
            'transactions.array' => __('The transaction field must be a valid array.'),
            'transactions.min' => __('At least one transaction row must be provided.'),

            'transactions.*.subject_id.required' => __('The Subject is required for each row.'),
            'transactions.*.subject_id.integer' => __('The Subject must be an integer.'),
            'transactions.*.subject_id.exists' => __('The selected Subject does not exist.'),

            'transactions.*.desc.string' => __('The Row description must be a valid string.'),
            'transactions.*.desc.max' => __('The Row description may not be greater than :max characters.'),

            'transactions.*.quantity.required' => __('The Quantity is required for each row.'),
            'transactions.*.quantity.numeric' => __('The Quantity must be a number.'),
            'transactions.*.quantity.min' => __('The Quantity must be at least :min.'),
        ];
    }
}
