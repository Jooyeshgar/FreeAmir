<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can($this->routeIs('documents.update') ? 'documents.update' : 'documents.create');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        if (is_string($this->input('date'))) {
            $this->merge(['date' => jalaliInputToGregorian($this->input('date'))]);
        }

        if (is_string($this->input('number')) && str_contains($this->input('number'), '/')) {
            $documentNumber = str_replace('/', '.', $this->input('number'));
            $this->merge([
                'number' => convertToFloat($documentNumber),
            ]);
        } elseif (is_scalar($this->input('number')) || $this->input('number') === null) {
            $this->merge([
                'number' => convertToFloat($this->input('number')) ?? null,
            ]);
        }

        // Convert debit and credit values to float for each document entry
        if ($this->input('transactions') === null) {
            $this->merge(['transactions' => []]);
        } elseif (is_array($this->input('transactions'))) {
            $transactions = collect($this->input('transactions'))->map(function ($transaction) {
                if (! is_array($transaction)) {
                    return $transaction;
                }
                $transaction['credit'] = $this->normalizeAmount($transaction['credit'] ?? null);
                $transaction['debit'] = $this->normalizeAmount($transaction['debit'] ?? null);

                return [
                    'debit' => $transaction['debit'],
                    'credit' => $transaction['credit'],
                    'value' => is_numeric($transaction['credit']) && is_numeric($transaction['debit'])
                        ? $transaction['credit'] - $transaction['debit'] : null,
                    'desc' => $transaction['desc'] ?? null,
                    'subject_id' => $transaction['subject_id'] ?? null,
                    'transaction_id' => isset($transaction['transaction_id']) ? (int) $transaction['transaction_id'] : null,
                ];
            });
            $this->merge(['transactions' => $transactions->toArray()]);
        }
    }

    private function normalizeAmount(mixed $value): mixed
    {
        if ($value === null) {
            return 0;
        }
        if (! is_scalar($value)) {
            return $value;
        }

        $normalized = str_replace(['٫', '٬', ',', ' '], ['.', '', '', ''], toEnglish((string) $value));

        return preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) ? (float) $normalized : $value;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
                        return $query->where('fiscal_year_id', getActiveFiscalYear());
                    })
                    ->ignore($this->route('document')),
            ],
            'date' => 'required|string|date',
            'transactions' => 'array',
            'transactions.*' => 'array',
            'transactions.*.subject_id' => ['required', Rule::exists('subjects', 'id')->where('fiscal_year_id', getActiveFiscalYear())],
            'transactions.*.transaction_id' => ['nullable', 'integer', Rule::exists('transactions', 'id')->where('document_id', $this->route('document') ?? 0)],
            'transactions.*.debit' => 'nullable|required_without:transactions.*.credit|decimal:0,2|min:0',
            'transactions.*.credit' => 'nullable|required_without:transactions.*.debit|decimal:0,2|min:0',
            'transactions.*.desc' => 'required|string',
        ];
    }
}
