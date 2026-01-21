<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_id' => 'required|exists:documents,id',
            'user_id' => 'nullable|exists:users,id',
            'title' => 'nullable|string|max:255',
            'file' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('Please select a file'),
            'file.mimes' => __('Only images and PDF files are allowed'),
            'file.max' => __('File size must not exceed 5MB'),
        ];
    }
}
