<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('logs')) {
            $this->merge(['logs' => [$this->all()]]);
        }
    }

    public function rules(): array
    {
        return [
            'logs' => ['required', 'array', 'min:1'],
            'logs.*.employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', getActiveCompany()),
            ],
            'logs.*.log_date' => ['required', 'date_format:Y-m-d'],
            'logs.*.entry_time' => ['nullable', 'date_format:H:i'],
            'logs.*.exit_time' => ['nullable', 'date_format:H:i', 'after_or_equal:logs.*.entry_time'],
            'logs.*.is_manual' => ['sometimes', 'boolean'],
            'logs.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
