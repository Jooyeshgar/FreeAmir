<?php

namespace App\Http\Requests;

use App\Enums\EmployeeDutyStatus;
use App\Enums\EmployeeEducationLevel;
use App\Enums\EmployeeEmploymentType;
use App\Enums\EmployeeGender;
use App\Enums\EmployeeInsuranceType;
use App\Enums\EmployeeMaritalStatus;
use App\Enums\EmployeeNationality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Identity
            'code' => ['required', 'string', 'max:20', Rule::unique('employees', 'code')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'national_code' => ['nullable', 'string', 'size:10', Rule::unique('employees', 'national_code')],
            'passport_number' => ['nullable', 'string', 'max:20'],
            'nationality' => ['required', new Enum(EmployeeNationality::class)],
            'gender' => ['nullable', new Enum(EmployeeGender::class)],
            'marital_status' => ['nullable', new Enum(EmployeeMaritalStatus::class)],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'duty_status' => ['nullable', new Enum(EmployeeDutyStatus::class)],

            // Contact
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],

            // Insurance
            'insurance_number' => ['nullable', 'string', 'max:20'],
            'insurance_type' => ['nullable', new Enum(EmployeeInsuranceType::class)],

            // Banking
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'card_number' => ['nullable', 'string', 'max:20'],
            'shaba_number' => ['nullable', 'string', 'max:30'],

            // Education
            'education_level' => ['nullable', new Enum(EmployeeEducationLevel::class)],
            'field_of_study' => ['nullable', 'string', 'max:100'],

            // Employment
            'employment_type' => ['nullable', new Enum(EmployeeEmploymentType::class)],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],

            // Org
            'org_chart_id' => ['nullable', 'integer', 'exists:org_charts,id'],
            'work_site_id' => ['required', 'integer', 'exists:work_sites,id'],
            'work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
            'contract_framework_id' => ['nullable', 'integer', 'exists:work_site_contracts,id'],

            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active'),
        ]);
    }
}
