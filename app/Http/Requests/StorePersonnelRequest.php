<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonnelRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Change to authorization logic if needed
    }

    public function rules()
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'personnel_code' => 'required|unique:personnel',
            'father_name' => 'required',
            'nationality' => 'required|in:iranian,non_iranian',
            'identity_number' => 'required',
            'national_code' => 'required',
            'passport_number' => 'required',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'gender' => 'required|in:female,male,other',
            'contact_number' => 'required',
            'address' => 'required',
            'insurance_number' => 'required',
            'insurance_type' => 'required|in:social_security,other',
            'children_count' => 'nullable|integer',
            'bank_id' => 'required|exists:banks,id',
            'account_number' => 'required',
            'card_number' => 'required',
            'iban' => 'required',
            'detailed_code' => 'required',
            'contract_start_date' => 'nullable|date',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'contract_type' => 'required|in:official,contract,other',
            'birth_place' => 'nullable',
            'organizational_chart_id' => 'required|exists:organizational_charts,id',
            'military_status' => 'required|in:not_subject,completed,in_progress',
            'workhouse_id' => 'required|exists:workhouses,id',
        ];
    }
}
