<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrganizationalChartRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'supervisor' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'name',
            'supervisor' => 'supervisor',
            'description' => 'description',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The name is required.',
            'supervisor.string' => 'The supervisor must be a string.',
            'description.string' => 'The description must be a string.',
        ];
    }
}
