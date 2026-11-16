<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class TrialBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dateRule = function (string $attribute, mixed $value, $fail): void {
            try {
                jalaliInputToGregorian((string) $value, $attribute);
            } catch (ValidationException) {
                $fail(__('validation.date_format', [
                    'attribute' => str_replace('_', ' ', $attribute),
                    'format' => 'Y/m/d',
                ]));
            }
        };

        return [
            'start_document_number' => ['nullable', 'numeric'],
            'end_document_number' => ['nullable', 'numeric'],
            'start_date' => ['bail', 'nullable', 'string', $dateRule],
            'end_date' => ['bail', 'nullable', 'string', $dateRule],
            'parent_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'include_children' => ['nullable', 'boolean'],
            'subject_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('start_document_number') && $this->filled('end_document_number')) {
                if ((int) $this->start_document_number > (int) $this->end_document_number) {
                    $validator->errors()->add(
                        'start_document_number',
                        __('Start document number cannot be greater than end document number.')
                    );
                }
            }

            if ($this->filled('start_date') && $this->filled('end_date')
                && ! $validator->errors()->hasAny(['start_date', 'end_date'])) {
                $start = jalaliInputToGregorian($this->input('start_date'), 'start_date');
                $end = jalaliInputToGregorian($this->input('end_date'), 'end_date');
                if ($start > $end) {
                    $validator->errors()->add('start_date', __('Start date cannot be greater than end date.'));
                }
            }
        });
    }
}
