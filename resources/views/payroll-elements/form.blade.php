<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="title" id="title" title="{{ __('Title') }}" :value="old('title', $payrollElement->title ?? '')" placeholder="{{ __('e.g. Housing Allowance') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <label class="label" for="system_code">
            <span class=" font-medium">{{ __('System Code') }}</span>
        </label>
        <select name="system_code" id="system_code" class="select  w-full" required>
            <option value="">{{ __('Select System Code') }}</option>
            @foreach ([
        'CHILD_ALLOWANCE' => __('Child Allowance'),
        'HOUSING_ALLOWANCE' => __('Housing Allowance'),
        'FOOD_ALLOWANCE' => __('Food Allowance'),
        'MARRIAGE_ALLOWANCE' => __('Marriage Allowance'),
        'OVERTIME' => __('Overtime'),
        'FRIDAY_PAY' => __('Friday Pay'),
        'HOLIDAY_PAY' => __('Holiday Pay'),
        'MISSION_PAY' => __('Mission Pay'),
        'INSURANCE_EMP' => __('Employee Insurance'),
        'INSURANCE_EMP2' => __('Employee Insurance 2'),
        'UNEMPLOYMENT_INS' => __('Unemployment Insurance'),
        'INCOME_TAX' => __('Income Tax'),
        'ABSENCE_DEDUCTION' => __('Absence Deduction'),
        'OTHER' => __('Other'),
    ] as $value => $label)
                <option value="{{ $value }}" @selected(old('system_code', $payrollElement->system_code ?? '') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('system_code')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-span-2 md:col-span-1">
        <label class="label" for="category">
            <span class=" font-medium">{{ __('Category') }}</span>
        </label>
        <select name="category" id="category" class="select  w-full" required>
            <option value="">{{ __('Select Category') }}</option>
            <option value="earning" @selected(old('category', $payrollElement->category ?? '') === 'earning')>{{ __('Earning') }}</option>
            <option value="deduction" @selected(old('category', $payrollElement->category ?? '') === 'deduction')>{{ __('Deduction') }}</option>
        </select>
        @error('category')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-span-2 md:col-span-1">
        <label class="label" for="calc_type">
            <span class=" font-medium">{{ __('Calculation Type') }}</span>
        </label>
        <select name="calc_type" id="calc_type" class="select  w-full" required>
            <option value="">{{ __('Select Calculation Type') }}</option>
            <option value="fixed" @selected(old('calc_type', $payrollElement->calc_type ?? '') === 'fixed')>{{ __('Fixed') }}</option>
            <option value="daily" @selected(old('calc_type', $payrollElement->calc_type ?? '') === 'daily')>{{ __('Daily') }}</option>
            <option value="percentage" @selected(old('calc_type', $payrollElement->calc_type ?? '') === 'percentage')>{{ __('Percentage') }}</option>
        </select>
        @error('calc_type')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Formula field: hidden from UI but kept for future use --}}
    <div style="display: none;">
        <x-input name="formula" id="formula" title="{{ __('Formula') }}" :value="old('formula', $payrollElement->formula ?? '')" placeholder="{{ __('e.g. BASE_SALARY * 0.1') }}" />
    </div>

    {{-- Default Amount / Percentage — label and attributes change based on calc_type --}}
    <div class="col-span-2 md:col-span-1" id="default_amount_wrapper">
        <label class="label" for="default_amount">
            <span class=" font-medium" id="default_amount_label">{{ __('Default Amount') }}</span>
        </label>
        <input
            type="number"
            name="default_amount"
            id="default_amount"
            class="input  w-full"
            value="{{ old('default_amount', $payrollElement->default_amount ?? '') }}"
            placeholder="0"
            step="0.01"
            min="0"
        />
        @error('default_amount')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <script>
    (function () {
        var calcType = document.getElementById('calc_type');
        var label = document.getElementById('default_amount_label');
        var input = document.getElementById('default_amount');

        function updateField() {
            if (calcType.value === 'percentage') {
                label.textContent = '{{ __('Percentage (%)') }}';
                input.placeholder = '{{ __('e.g. 10') }}';
                input.max = '100';
            } else {
                label.textContent = '{{ __('Default Amount') }}';
                input.placeholder = '0';
                input.removeAttribute('max');
            }
        }

        calcType.addEventListener('change', updateField);
        updateField();
    })();
    </script>

    <div class="col-span-2 md:col-span-1">
        <x-input name="gl_account_code" id="gl_account_code" title="{{ __('GL Account Code') }}" :value="old('gl_account_code', $payrollElement->gl_account_code ?? '')" placeholder="{{ __('e.g. 3210') }}" />
    </div>

    <div class="col-span-2 flex flex-wrap gap-6">
        <label class="label cursor-pointer gap-2">
            <input type="checkbox" name="is_taxable" value="1" class="checkbox" @checked(old('is_taxable', $payrollElement->is_taxable ?? false)) />
            <span>{{ __('Is Taxable') }}</span>
        </label>

        <label class="label cursor-pointer gap-2">
            <input type="checkbox" name="is_insurable" value="1" class="checkbox" @checked(old('is_insurable', $payrollElement->is_insurable ?? false)) />
            <span>{{ __('Is Insurable') }}</span>
        </label>

        <label class="label cursor-pointer gap-2">
            <input type="checkbox" name="show_in_payslip" value="1" class="checkbox" @checked(old('show_in_payslip', $payrollElement->show_in_payslip ?? true)) />
            <span>{{ __('Show in Payslip') }}</span>
        </label>
    </div>
</div>
