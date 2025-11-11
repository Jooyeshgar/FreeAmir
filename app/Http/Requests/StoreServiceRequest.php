<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'nullable|unique:services,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s\-\:\.]*$/u',
            'group' => 'required|exists:service_groups,id|integer',
            'selling_price' => [
                'nullable',
                'string',
                'regex:/^(\d{1,3}(,\d{3})*|\d+)(\\.\\d+)?$/',
            ],
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
            'sstid' => 'nullable|string',
        ];
    }

    /**
     * Get the validated data with proper type casting.
     */
    public function getValidatedData(): array
    {
        $validatedData = $this->validated();

        $validatedData['code'] = empty($validatedData['code']) ? Service::max('code') ?? 1 : $validatedData['code'];
        $validatedData['selling_price'] = convertToFloat(empty($validatedData['selling_price']) ? 0 : $validatedData['selling_price']);
        $validatedData['vat'] = convertToFloat(empty($validatedData['vat']) ? 0 : $validatedData['vat']);
        $validatedData['sstid'] = empty($validatedData['sstid']) ? null : $validatedData['sstid'];

        return $validatedData;
    }
}
