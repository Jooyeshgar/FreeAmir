<?php

namespace App\Http\Requests;

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

        // Cast invoice_type (0/1 string) to integer boolean-like
        if ($this->has('invoice_type')) {
            $type = $this->input('invoice_type');
            $this->merge(['invoice_type' => is_bool($type) ? (int)$type : (int)convertToInt($type)]);
        }

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
            'title' => 'required|string|min:2|max:255',
            'date' => 'required|date',

            // Invoice basics
            'invoice_type' => ['required', Rule::in([0, 1])],
            'customer_id' => 'required|exists:customers,id|integer',
            'invoice_id'   => 'nullable|integer|exists:invoices,id',
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
        ];
    }
}
