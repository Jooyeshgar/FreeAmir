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
            'iban' => ['nullable', Rule::unique('bank_accounts', 'iban')->where(fn ($query) => $query->where('company_id', $this->company_id))->ignore($this->bank_account),
                function ($attribute, $value, $fail) {
                    $ibans = [
                        'fa' => [
                            'country' => 'IR',
                            'length' => 26,
                        ],
                        'de' => [
                            'country' => 'DE',
                            'length' => 22,
                        ],
                        'gr' => [
                            'country' => 'GR',
                            'length' => 27,
                        ],
                        'el' => [
                            'country' => 'GR',
                            'length' => 27,
                        ],
                        'en' => [
                            'country' => 'GB',
                            'length' => 22,
                        ],
                    ];
                    $config = $ibans[app()->getLocale()] ?? $ibans['fa'];
                    $value = strtoupper(trim($value));
                    if (strlen($value) !== $config['length'] || ! str_starts_with($value, $config['country'])) {
                        $fail(__('Invalid IBAN.'));

                        return;
                    }

                    $iban = substr($value, 4).substr($value, 0, 4);
                    $iban = preg_replace_callback('/[A-Z]/', fn ($m) => ord($m[0]) - 55, $iban);
                    if (bcmod($iban, '97') != 1) {
                        $fail(__('Invalid IBAN.'));
                    }
                }],
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
