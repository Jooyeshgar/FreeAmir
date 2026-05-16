<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
            'number' => [
                'nullable',
                'decimal:0,2',
                Rule::unique('documents', 'number')->where('company_id', getActiveCompany()),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'transactions' => ['required', 'array', 'min:2'],
            'transactions.*.subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('company_id', getActiveCompany()),
            ],
            'transactions.*.value' => ['required', 'decimal:0,2'],
            'transactions.*.desc' => ['nullable', 'string'],
        ];
    }
}
