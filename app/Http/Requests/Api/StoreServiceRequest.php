<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'sstid' => ['nullable', 'string', 'max:100'],
            'group' => ['required', 'integer', 'min:1'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:150'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
