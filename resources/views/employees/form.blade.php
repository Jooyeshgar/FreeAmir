{{-- Shared form fields for Employee create / edit --}}

{{-- Section: Identity --}}
<div class="divider text-sm font-semibold">{{ __('Identity') }}</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">

    <div>
        <x-input name="code" id="code" title="{{ __('Personnel Code') }}" :value="old('code', $employee->code ?? '')" placeholder="EMP-0001" required />
    </div>

    <div>
        <x-input name="first_name" id="first_name" title="{{ __('First Name') }}" :value="old('first_name', $employee->first_name ?? '')" required />
    </div>

    <div>
        <x-input name="last_name" id="last_name" title="{{ __('Last Name') }}" :value="old('last_name', $employee->last_name ?? '')" required />
    </div>

    <div>
        <x-input name="father_name" id="father_name" title="{{ __('Father Name') }}" :value="old('father_name', $employee->father_name ?? '')" />
    </div>

    <div>
        <x-input name="national_code" id="national_code" title="{{ __('National Code') }}" :value="old('national_code', $employee->national_code ?? '')" placeholder="0000000000" />
    </div>

    <div>
        <x-input name="passport_number" id="passport_number" title="{{ __('Passport Number') }}" :value="old('passport_number', $employee->passport_number ?? '')" />
    </div>

    <div>
        <x-select name="nationality" id="nationality" title="{{ __('Nationality') }}" :options="$nationalities" :selected="old('nationality', $employee->nationality?->value ?? 'iranian')" required />
    </div>

    <div>
        <x-select name="gender" id="gender" title="{{ __('Gender') }}" :options="$genders" :selected="old('gender', $employee->gender?->value ?? '')" />
    </div>

    <div>
        <x-select name="marital_status" id="marital_status" title="{{ __('Marital Status') }}" :options="$maritalStatuses" :selected="old('marital_status', $employee->marital_status?->value ?? '')" />
    </div>

    <div>
        <x-input name="children_count" id="children_count" type="number" title="{{ __('Children Count') }}" :value="old('children_count', $employee->children_count ?? 0)" placeholder="0" />
    </div>

    <div>
        <x-date-picker name="birth_date" id="birth_date" title="{{ __('Birth Date') }}" :value="old('birth_date', isset($employee) ? $employee->birth_date?->format('Y-m-d') : '')" />
    </div>

    <div>
        <x-input name="birth_place" id="birth_place" title="{{ __('Birth Place') }}" :value="old('birth_place', $employee->birth_place ?? '')" />
    </div>

    <div>
        <x-select name="duty_status" id="duty_status" title="{{ __('Duty Status') }}" :options="$dutyStatuses" :selected="old('duty_status', $employee->duty_status?->value ?? '')" />
    </div>

</div>

{{-- Section: Contact --}}
<div class="divider text-sm font-semibold">{{ __('Contact') }}</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div>
        <x-input name="phone" id="phone" title="{{ __('Phone') }}" :value="old('phone', $employee->phone ?? '')" placeholder="09xxxxxxxxx" />
    </div>

    <div class="md:col-span-2">
        <x-textarea name="address" id="address" title="{{ __('Address') }}" :value="old('address', $employee->address ?? '')" />
    </div>

</div>

{{-- Section: Insurance --}}
<div class="divider text-sm font-semibold">{{ __('Insurance') }}</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div>
        <x-input name="insurance_number" id="insurance_number" title="{{ __('Insurance Number') }}" :value="old('insurance_number', $employee->insurance_number ?? '')" />
    </div>

    <div>
        <x-select name="insurance_type" id="insurance_type" title="{{ __('Insurance Type') }}" :options="$insuranceTypes" :selected="old('insurance_type', $employee->insurance_type?->value ?? '')" />
    </div>

</div>

{{-- Section: Banking --}}
<div class="divider text-sm font-semibold">{{ __('Banking') }}</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div>
        <x-input name="bank_name" id="bank_name" title="{{ __('Bank Name') }}" :value="old('bank_name', $employee->bank_name ?? '')" />
    </div>

    <div>
        <x-input name="bank_account" id="bank_account" title="{{ __('Bank Account') }}" :value="old('bank_account', $employee->bank_account ?? '')" />
    </div>

    <div>
        <x-input name="card_number" id="card_number" title="{{ __('Card Number') }}" :value="old('card_number', $employee->card_number ?? '')" placeholder="xxxx-xxxx-xxxx-xxxx" />
    </div>

    <div>
        <x-input name="shaba_number" id="shaba_number" title="{{ __('Shaba Number') }}" :value="old('shaba_number', $employee->shaba_number ?? '')" placeholder="IR..." />
    </div>

</div>

{{-- Section: Education --}}
<div class="divider text-sm font-semibold">{{ __('Education') }}</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div>
        <x-select name="education_level" id="education_level" title="{{ __('Education Level') }}" :options="$educationLevels" :selected="old('education_level', $employee->education_level?->value ?? '')" />
    </div>

    <div>
        <x-input name="field_of_study" id="field_of_study" title="{{ __('Field of Study') }}" :value="old('field_of_study', $employee->field_of_study ?? '')" />
    </div>

</div>

{{-- Section: Employment --}}
<div class="divider text-sm font-semibold">{{ __('Employment') }}</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">

    <div>
        <x-select name="employment_type" id="employment_type" title="{{ __('Employment Type') }}" :options="$employmentTypes" :selected="old('employment_type', $employee->employment_type?->value ?? '')" />
    </div>

    <div>
        <x-date-picker name="contract_start_date" id="contract_start_date" title="{{ __('Contract Start Date') }}" :value="old('contract_start_date', isset($employee) ? $employee->contract_start_date?->format('Y-m-d') : '')" />
    </div>

    <div>
        <x-date-picker name="contract_end_date" id="contract_end_date" title="{{ __('Contract End Date') }}" :value="old('contract_end_date', isset($employee) ? $employee->contract_end_date?->format('Y-m-d') : '')" />
    </div>

</div>

{{-- Section: Organization --}}
<div class="divider text-sm font-semibold">{{ __('Organization') }}</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">

    <div>
        <x-select name="work_site_id" id="work_site_id" title="{{ __('Work Site') }}" :options="$workSites->mapWithKeys(fn($ws) => [$ws->id => $ws->name])->toArray()" :selected="old('work_site_id', $employee->work_site_id ?? '')" required />
    </div>

    <div>
        <x-select name="org_chart_id" id="org_chart_id" title="{{ __('Org Chart Position') }}" :options="['' => __('— None —')] + $orgCharts->mapWithKeys(fn($oc) => [$oc->id => $oc->title])->toArray()" :selected="old('org_chart_id', $employee->org_chart_id ?? '')" />
    </div>

    <div>
        <x-select name="contract_framework_id" id="contract_framework_id" title="{{ __('Contract Framework') }}" :options="['' => __('— None —')] + $workSiteContracts->mapWithKeys(fn($cf) => [$cf->id => $cf->name])->toArray()" :selected="old('contract_framework_id', $employee->contract_framework_id ?? '')" />
    </div>

</div>

{{-- Active toggle --}}
<div class="mt-4">
    <label class="label cursor-pointer justify-start gap-3">
        <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-primary"
            {{ old('is_active', $employee->is_active ?? true) ? 'checked' : '' }} />
        <span class="label-text font-medium">{{ __('Active') }}</span>
    </label>
</div>
