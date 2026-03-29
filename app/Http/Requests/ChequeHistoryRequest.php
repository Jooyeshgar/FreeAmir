<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChequeHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cheque_id' => ['required', 'exists:cheques,id'],
            'action_type' => ['required', 'string', 'max:255'],
            'from_status' => ['nullable', 'string', 'max:255'],
            'to_status' => ['nullable', 'string', 'max:255'],
            'action_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'desc' => ['nullable', 'string'],
        ];
    }
}
