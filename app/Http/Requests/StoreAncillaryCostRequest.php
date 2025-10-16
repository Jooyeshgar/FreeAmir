<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAncillaryCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date' => convertToGregorian($this->input('date')),
            'invoice_id' => convertToInt($this->input('invoice_id')),
            'amount' => convertToFloat($this->input('amount')),
            'description' => $this->input('description'),
        ]);
    }

    public function rules(): array
    {
        return [
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'description' => 'required|string',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'description.string' => __('The Description field must be a valid string.'),
            'description.required' => __('The Description field is required.'),
            'date.required' => __('The Date field is required.'),
            'date.date' => __('The Date field must be a valid date.'),
            'invoice_id.integer' => __('The invoice ID field must be an integer.'),
            'invoice_id.exists' => __('The selected invoice ID is invalid.'),
            'amount.required' => __('The Amount field is required.'),
            'amount.numeric' => __('The Amount field must be a number.'),
            'amount.min' => __('The Amount must be at least :min.'),
        ];
    }
}
