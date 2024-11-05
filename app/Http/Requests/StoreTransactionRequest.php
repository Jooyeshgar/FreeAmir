<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('documents.create');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'date' => convertToGregorian($this->input('date'))
        ]);

        $this->merge([
            'number' => convertToFloat($this->input('number'))
        ]);

        // Convert debit and credit values to float for each document entry
        if ($this->has('documents')) {
            $documents = collect($this->input('documents'))->map(function ($document) {
                return [
                    'debit' => convertToFloat($document['debit']),
                    'credit' => convertToFloat($document['credit']),
                    'desc' => $document['desc'],
                    'subject_id' => $document['subject_id'],
                ];
            });
            $this->merge(['documents' => $documents->toArray()]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|min:3|max:255',
            'number' => [
                'required',
                'integer',
                Rule::unique('documents', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', session('active-company-id'));
                    })
                    ->ignore($this->request->get('document_id')), // Ignore the current document ID if updating
            ],
            'date' => 'required',
            'documents.*.subject_id' => 'required|exists:subjects,id',
            'documents.*.debit' => 'nullable|required_without:transactions.*.credit|integer|min:0',
            'documents.*.credit' => 'nullable|required_without:transactions.*.debit|integer|min:0',
            'documents.*.desc' => 'required|string',
        ];
    }
}
