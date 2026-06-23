<?php

namespace App\Http\Requests;

use App\Enums\BankAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->iban) {
            $this->merge([
                'iban' => strtoupper(str_replace(' ', '', $this->iban)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'number' => 'nullable|string|max:40',
            'iban' => ['nullable', Rule::unique('bank_accounts', 'iban')->ignore($this->bank_account),
                function ($attribute, $value, $fail) {
                    $value = strtoupper(str_replace(' ', '', trim($value)));
                    $ibanLengths = [
                        'IR' => 26,
                        'DE' => 22,
                        'GB' => 22,
                        'GR' => 27,
                    ];

                    if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $value)) {
                        $fail(__('Invalid IBAN.'));

                        return;
                    }

                    $country = substr($value, 0, 2);

                    if (! isset($ibanLengths[$country])) {
                        $fail(__('Unsupported IBAN country.'));

                        return;
                    }

                    if (strlen($value) !== $ibanLengths[$country]) {
                        $fail(__('Invalid IBAN.'));

                        return;
                    }

                    $iban = substr($value, 4).substr($value, 0, 4); // Move first 4 chars to the end
                    $iban = preg_replace_callback('/[A-Z]/', fn ($match) => ord($match[0]) - 55, $iban); // Replace letters with numbers (A=10 ... Z=35)

                    if (bcmod($iban, '97') != 1) {
                        $fail(__('Invalid IBAN.'));
                    }
                },
            ],
            'type' => ['required', new Enum(BankAccountType::class)],
            'owner' => ['nullable', 'string', 'regex:/^[\w\d\s]*$/u'],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'bank_branch' => ['nullable', 'string', 'regex:/^[\w\d\s]*$/u'],
            'bank_address' => ['nullable', 'string', 'max:150'],
            'bank_phone' => ['nullable', 'numeric'],
            'bank_web_page' => ['nullable', 'url', 'max:200'],
            'desc' => ['nullable', 'string', 'max:150'],
        ];
    }
}
