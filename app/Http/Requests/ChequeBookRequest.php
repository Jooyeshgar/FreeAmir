<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChequeBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'is_sayad' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'desc' => ['nullable', 'string'],
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'issued_at' => convertToGregorian($this->input('issued_at')),
            'is_sayad' => $this->boolean('is_sayad'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
