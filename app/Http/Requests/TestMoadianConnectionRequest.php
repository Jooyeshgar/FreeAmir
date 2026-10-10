<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestMoadianConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $this->user()->can('access-super-admin-panel')
            || $this->user()->companies()->whereKey($company->id)->exists();
    }

    public function rules(): array
    {
        return [
            'moadian_username' => ['required', 'string', 'max:20'],
            'tax_id' => ['required', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            'certificate' => ['required', 'file', 'extensions:crt,cer', 'max:1024'],
            'private_key' => ['required', 'file', 'extensions:pem', 'max:1024'],
        ];
    }
}
