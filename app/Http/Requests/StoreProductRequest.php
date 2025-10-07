<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'code' => 'required|unique:products,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'group' => 'required|exists:product_groups,id|integer',
            'location' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'quantity' => [
                'nullable',
                'string',
                'regex:/^(\d{1,3}(,\d{3})*|\d+)(\\.\\d+)?$/',
            ],
            'quantity_warning' => [
                'nullable',
                'string',
                'regex:/^(\d{1,3}(,\d{3})*|\d+)(\\.\\d+)?$/',
            ],
            'purchace_price' => [
                'nullable',
                'string',
                'regex:/^(\d{1,3}(,\d{3})*|\d+)(\\.\\d+)?$/',
            ],
            'selling_price' => [
                'nullable',
                'string',
                'regex:/^(\d{1,3}(,\d{3})*|\d+)(\\.\\d+)?$/',
            ],
            'discount_formula' => 'nullable|max:100|string|regex:/^[\w\d\s\-\:\.]*$/u',
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
            'sstid' => 'nullable|string',
            'websites' => 'nullable|array',
            'websites.*.link' => 'required|url',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'oversell' => $this->has('oversell') ? 1 : 0,
        ]);
    }

    /**
     * Get the validated data with proper type casting.
     */
    public function getValidatedData(): array
    {
        $validatedData = $this->validated();

        $validatedData['oversell'] = $this->has('oversell') ? 1 : 0;
        $validatedData['purchace_price'] = convertToFloat(empty($validatedData['purchace_price']) ? 0 : $validatedData['purchace_price']);
        $validatedData['selling_price'] = convertToFloat(empty($validatedData['selling_price']) ? 0 : $validatedData['selling_price']);
        $validatedData['quantity_warning'] = convertToFloat(empty($validatedData['quantity_warning']) ? 0 : str_replace(',', '', $validatedData['quantity_warning']));
        $validatedData['quantity'] = convertToFloat(empty($validatedData['quantity']) ? 0 : str_replace(',', '', $validatedData['quantity']));
        $validatedData['vat'] = convertToFloat(empty($validatedData['vat']) ? 0 : $validatedData['vat']);
        $validatedData['sstid'] = empty($validatedData['sstid']) ? null : $validatedData['sstid'];

        return $validatedData;
    }
}
