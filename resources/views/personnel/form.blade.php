<div class="grid grid-cols-2 bg-gray-100">
    <!-- Identity Information -->
    <div class="bg-gray-100 p-2 min-h-full">
        <div>
            <h1>مشخصات هویتی</h1>
            <div class="grid grid-cols-2 gap-1">
                <!-- First Name -->
                <div>
                    <x-form-input title="{{ __('First Name') }}" name="first_name" place-holder="{{ __('First Name') }}"
                        :value="old('first_name', $personnel->first_name ?? '')" />
                </div>
                <!-- Last Name -->
                <div>
                    <x-form-input title="{{ __('Last Name') }}" name="last_name" place-holder="{{ __('Last Name') }}"
                        :value="old('last_name', $personnel->last_name ?? '')" />
                </div>
                <!-- Personnel Code -->
                <div>
                    <x-form-input title="{{ __('Personnel Code') }}" name="personnel_code"
                        place-holder="{{ __('Personnel Code') }}" :value="old('personnel_code', $personnel->personnel_code ?? '')" />
                </div>
                <!-- Father's Name -->
                <div>
                    <x-form-input title="{{ __('Father Name') }}" name="father_name"
                        place-holder="{{ __('Father Name') }}" :value="old('father_name', $personnel->father_name ?? '')" />
                </div>
                <!-- Nationality -->
                <div>
                    <x-form-select title="{{ __('Nationality') }}" name="nationality"
                        :options="['iranian' => __('Iranian'), 'non_iranian' =>  __('Non Iranian')]"
                        :selected="old('nationality', $personnel->nationality ?? '')" />
                  
                </div>
                <!-- National Code -->
                <div>
                    <x-form-input title="{{ __('National Code') }}" name="national_code"
                        place-holder="{{ __('National Code') }}" :value="old('national_code', $personnel->national_code ?? '')" />
                </div>
                <!-- Identity Number -->
                <div>
                    <x-form-input title="{{ __('Identity Number') }}" name="identity_number"
                        place-holder="{{ __('Identity Number') }}" :value="old('identity_number', $personnel->identity_number ?? '')" />
                </div>
                <!-- Passport Number -->
                <div>
                    <x-form-input title="{{ __('Passport Number') }}" name="passport_number"
                        place-holder="{{ __('Passport Number') }}" :value="old('passport_number', $personnel->passport_number ?? '')" />
                </div>
                <!-- Marital Status -->
                <div>
                    <x-form-select title="{{ __('Marital Status') }}" name="marital_status"
                        :options="['single' => __('single'), 'married' => __('married'), 'divorced' => __('divorced'), 'widowed' => __('widowed')]"
                        :selected="old('marital_status', $personnel->marital_status ?? '')" />
                </div>
                <!-- Gender -->
                <div>
                    <x-form-select title="{{ __('Gender') }}" name="gender"
                        :options="['female' => __('female'), 'male' => __('male'), 'other' => __('other')]"
                        :selected="old('gender', $personnel->gender ?? '')" />
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="bg-gray-200 p-2 rounded-xl">
        <div role="tablist" class="tabs tabs-boxed">
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="{{__('Additional Information')}}" checked />
            <div role="tabpanel" class="tab-content bg-gray-100 rounded-box p-2">
                <div class="grid grid-cols-3 gap-1">
                    <!-- Contact Number -->
                    <div>
                        <x-form-input title="{{ __('Contact Number') }}" name="contact_number"
                            place-holder="{{ __('Contact Number') }}" :value="old('contact_number', $personnel->contact_number ?? '')" />
                    </div>
                    <!-- Address -->
                    <div>
                        <x-form-input title="{{ __('Address') }}" name="address" place-holder="{{ __('Address') }}"
                            :value="old('address', $personnel->address ?? '')" />
                    </div>
                    <!-- Insurance Number -->
                    <div>
                        <x-form-input title="{{ __('Insurance Number') }}" name="insurance_number"
                            place-holder="{{ __('Insurance Number') }}" :value="old('insurance_number', $personnel->insurance_number ?? '')" />
                    </div>
                    <!-- Insurance Type -->
                    <div>
                        <x-form-select title="{{ __('Insurance Type') }}" name="insurance_type"
                            :options="['social_security' => 'تامین اجتماعی', 'other' => 'سایر']"
                            :selected="old('insurance_type', $personnel->insurance_type ?? '')" />
                    </div>
                    <!-- Children Count -->
                    <div>
                        <x-form-input title="{{ __('Children Count') }}" name="children_count"
                            place-holder="{{ __('Children Count') }}" type="number" :value="old('children_count', $personnel->children_count ?? '')" />
                    </div>
                    <!-- Bank -->
                    <div>
                        <x-form-select title="{{ __('Bank') }}" name="bank_id"
                            :options="$banks->pluck('name', 'id')"
                            :selected="old('bank_id', $personnel->bank_id ?? '')" />
                    </div>
                    <!-- Account Number -->
                    <div>
                        <x-form-input title="{{ __('Account Number') }}" name="account_number"
                            place-holder="{{ __('Account Number') }}" :value="old('account_number', $personnel->account_number ?? '')" />
                    </div>
                    <!-- Card Number -->
                    <div>
                        <x-form-input title="{{ __('Card Number') }}" name="card_number"
                            place-holder="{{ __('Card Number') }}" :value="old('card_number', $personnel->card_number ?? '')" />
                    </div>
                    <!-- IBAN -->
                    <div>
                        <x-form-input title="{{ __('IBAN') }}" name="iban" place-holder="{{ __('IBAN') }}"
                            :value="old('iban', $personnel->iban ?? '')" />
                    </div>
                </div>
            </div>

            <!-- Organizational Information -->
            <input type="radio" name="my_tabs_2" role="tab" class="tab whitespace-nowrap" aria-label="{{__('Organizational Information')}}" />
            <div role="tabpanel" class="tab-content bg-gray-100 rounded-box p-2">
                <div class="grid grid-cols-3 gap-1">
                    <!-- Detailed Code -->
                    <div>
                        <x-form-input title="{{ __('Detailed Code') }}" name="detailed_code"
                            place-holder="{{ __('Detailed Code') }}" :value="old('detailed_code', $personnel->detailed_code ?? '')" />
                    </div>
                    <!-- Contract Start Date -->
                    <div>
                        <x-form-input title="{{ __('Contract Start Date') }}" name="contract_start_date"
                            place-holder="{{ __('Contract Start Date') }}" type="date"
                            :value="old('contract_start_date', $personnel->contract_start_date ?? '')" />
                    </div>
                    <!-- Employment Type -->
                    <div>
                        <x-form-select title="{{ __('Employment Type') }}" name="employment_type"
                        :options="['full_time' => __('full time'), 'part_time' => __('part time'), 'contract' => __('contract')]"

                            :selected="old('employment_type',$personnel->employment_type??'')" />
                    </div>
                    <!-- Contract Type -->
                    <div>
                        <x-form-select title="{{ __('Contract Type') }}" name="contract_type"
                        :options="['official' => __('official'), 'contractual' => __('contractual'), 'temporary' => __('temporary')]"

                        :selected="old('contract_type',$personnel->contract_type??'')" />
                    </div>
                    <!-- Birth Place -->
                    <div>
                        <x-form-input title="{{ __('Birth Place') }}" name="birth_place"
                            place-holder="{{ __('Birth Place') }}" :value="old('birth_place', $personnel->birth_place ?? '')" />
                    </div>
                    <!-- Organizational Chart -->
                    <div>
                        <x-form-select title="{{ __('Organizational Chart') }}" name="organizational_chart_id"
                            :options="$organizationalCharts->pluck('name', 'id')"
                            :selected="old('organizational_chart_id', $personnel->organizational_chart_id ?? '')" />
                    </div>
                    <!-- Military Status -->
                    <div>
                        <x-form-select title="{{ __('Military Status') }}" name="military_status"
                            :options="['not_subject' => 'معاف', 'in_progress' => 'در حال خدمت', 'completed' => 'پایان خدمت']"
                            :selected="old('military_status', $personnel->military_status ?? '')" />
                    </div>
                    <!-- Workhouse -->
                    <div>
                        <x-form-select title="{{ __('Workhouse') }}" name="workhouse_id"
                            :options="$workhouses->pluck('name', 'id')"
                            :selected="old('workhouse_id', $personnel->workhouse_id ?? '')" />
                    </div>
                
                </div>
                <div>
                    <x-form-select title="{{ __('Salary Slips') }}" name="salary_slips" place-holder="{{ __('Select Salary Slips') }}" :multiple="true"
        :options="$salarySlips->pluck('name', 'id')" :selected="old('salary_slips', isset($personnel)?($personnel->salarySlips->pluck('id')->toArray() ?? []):[])" />

                    </div>
            </div>
        </div>
    </div>
</div>
