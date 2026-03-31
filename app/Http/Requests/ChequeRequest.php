<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'written_at' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'serial' => ['required', 'string', 'max:50'],
            'cheque_number' => ['required', 'string', 'max:100'],
            'sayad_number' => ['nullable', 'string', 'max:100'],
            'is_received' => ['nullable', 'boolean'],
            'desc' => ['nullable', 'string'],
            'customer_id' => ['required', 'exists:customers,id'],
            'transaction_id' => ['nullable', 'exists:transactions,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'written_at' => convertToGregorian($this->input('written_at')),
            'due_date' => convertToGregorian($this->input('due_date')),
            'is_received' => $this->boolean('is_received'),
        ]);
    }
}
