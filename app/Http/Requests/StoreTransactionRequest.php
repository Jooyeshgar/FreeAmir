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
            'date' => convertToGregorian($this->input('date')),
        ]);

        if ($this->has('number') && str_contains($this->input('number'), '/')) {
            $documentNumber = str_replace('/', '.', $this->input('number'));
            $this->merge([
                'number' => convertToFloat($documentNumber),
            ]);
        } else {
            $this->merge([
                'number' => convertToFloat($this->input('number')) ?? null,
            ]);
        }

        // Convert debit and credit values to float for each document entry
        if ($this->has('transactions')) {
            $transactions = collect($this->input('transactions'))->map(function ($transaction) {
                $transaction['credit'] = convertToFloat($transaction['credit']);
                $transaction['debit'] = convertToFloat($transaction['debit']);

                return [
                    'debit' => $transaction['debit'],
                    'credit' => $transaction['credit'],
                    'value' => $transaction['credit'] - $transaction['debit'],
                    'desc' => $transaction['desc'],
                    'subject_id' => (int) $transaction['subject_id'],
                    'transaction_id' => (int) $transaction['transaction_id'] ?? null,
                ];
            });
            $this->merge(['transactions' => $transactions->toArray()]);
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
                'nullable',
                'decimal:0,2',
                Rule::unique('documents', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', session('active-company-id'));
                    })
                    ->ignore($this->request->get('document_id')), // Ignore the current document ID if updating
            ],
            'date' => 'required',
            'transactions.*.subject_id' => 'required|exists:subjects,id',
            'transactions.*.debit' => 'nullable|required_without:transactions.*.credit|integer|min:0',
            'transactions.*.credit' => 'nullable|required_without:transactions.*.debit|integer|min:0',
            'transactions.*.desc' => 'required|string',
        ];
    }
}
