<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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
            'title' => 'required|string|min:3|max:255',
            'number' => 'required|integer|unique:documents,number'.($this->request->get('document_id')?','.$this->request->get('document_id'):''),
            'date' => 'required',
            'transactions.*.subject_id' => 'required|exists:subjects,id',
            'transactions.*.debit' => 'nullable|required_without:transactions.*.credit|integer|min:0',
            'transactions.*.credit' => 'nullable|required_without:transactions.*.debit|integer|min:0',
            'transactions.*.desc' => 'required|string',
        ];
    }
}
