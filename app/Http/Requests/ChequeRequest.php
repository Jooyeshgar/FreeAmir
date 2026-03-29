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
            'wrt_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'serial' => ['nullable', 'string', 'max:255'],
            'cheque_number' => ['nullable', 'integer'],
            'sayad_number' => ['nullable', 'numeric'],
            'is_received' => ['nullable', 'boolean'],
            'desc' => ['nullable', 'string'],
            'customer_id' => ['required', 'exists:customers,id'],
            'transaction_id' => ['nullable', 'exists:transactions,id'],
            'cheque_book_id' => ['required', 'exists:cheque_books,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_received' => $this->boolean('is_received'),
        ]);
    }
}
