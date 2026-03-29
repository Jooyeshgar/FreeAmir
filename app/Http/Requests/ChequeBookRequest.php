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
            'title' => ['required', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'is_sayad' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'company_id' => ['required', 'exists:companies,id'],
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_sayad' => $this->boolean('is_sayad'),
        ]);
    }
}
