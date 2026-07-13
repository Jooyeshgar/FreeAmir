<?php

namespace App\Http\Requests\Api;

use App\Enums\EmployeeDutyStatus;
use App\Enums\EmployeeEducationLevel;
use App\Enums\EmployeeEmploymentType;
use App\Enums\EmployeeGender;
use App\Enums\EmployeeInsuranceType;
use App\Enums\EmployeeMaritalStatus;
use App\Enums\EmployeeNationality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('employees', 'code')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'national_code' => ['nullable', 'string', 'size:10', Rule::unique('employees', 'national_code')],
            'passport_number' => ['nullable', 'string', 'max:20'],
            'nationality' => ['required', Rule::in(EmployeeNationality::valueNames())],
            'gender' => ['nullable', Rule::in(EmployeeGender::valueNames())],
            'marital_status' => ['nullable', Rule::in(EmployeeMaritalStatus::valueNames())],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'duty_status' => ['nullable', Rule::in(EmployeeDutyStatus::valueNames())],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'insurance_number' => ['nullable', 'string', 'max:20'],
            'insurance_type' => ['nullable', Rule::in(EmployeeInsuranceType::valueNames())],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'card_number' => ['nullable', 'string', 'max:20'],
            'shaba_number' => ['nullable', 'string', 'max:30'],
            'education_level' => ['nullable', Rule::in(EmployeeEducationLevel::valueNames())],
            'field_of_study' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', Rule::in(EmployeeEmploymentType::valueNames())],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'org_chart_id' => ['nullable', 'integer', Rule::exists('org_charts', 'id')->where('company_id', getActiveCompany())],
            'work_site_id' => ['required', 'integer', Rule::exists('work_sites', 'id')->where('company_id', getActiveCompany())],
            'work_shift_id' => ['required', 'integer', Rule::exists('work_shifts', 'id')->where('company_id', getActiveCompany())],
            'contract_framework_id' => ['nullable', 'integer', 'exists:work_site_contracts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'leave_remain' => ['nullable', 'numeric', 'min:0', 'max:15000'],
            'device_id' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        $casts = [
            'nationality' => EmployeeNationality::class,
            'gender' => EmployeeGender::class,
            'marital_status' => EmployeeMaritalStatus::class,
            'duty_status' => EmployeeDutyStatus::class,
            'insurance_type' => EmployeeInsuranceType::class,
            'education_level' => EmployeeEducationLevel::class,
            'employment_type' => EmployeeEmploymentType::class,
        ];

        foreach ($casts as $field => $enumClass) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = $enumClass::fromName($data[$field]);
            }
        }

        return $data;
    }
}
