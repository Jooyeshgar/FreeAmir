<div class="grid grid-cols-1 bg-gray-100">
    <div class="bg-gray-100 p-2 min-h-full">
        <div>
            <div class="grid grid-cols-2 gap-1">
                <div>
                    <x-form-input title="{{ __('Name') }}" name="name"
                        place-holder="{{ __('Benefit or Deduction Name') }}" :value="old('name', $benefitsDeduction->name ?? '')" />
                </div>
                <div>
                    <x-form-select title="{{ __('Type') }}" name="type" :options="['benefit' => __('Benefit'), 'deduction' => __('Deduction')]" :selected="old('type', $benefitsDeduction->type ?? '')" />
                </div>
                <div>
                    <x-form-select title="{{ __('Calculation') }}" name="calculation" :options="['fixed' => __('Fixed'), 'hourly' => __('Hourly'), 'manual' => __('Manual')]" :selected="old('calculation', $benefitsDeduction->calculation ?? '')" />
                </div>
                <div>
                    <x-form-checkbox title="{{ __('Insurance Included') }}" name="insurance_included"
                        :checked="old('insurance_included', $benefitsDeduction->insurance_included ?? false)" />
                </div>
                <div>
                    <x-form-checkbox title="{{ __('Tax Included') }}" name="tax_included" :checked="old('tax_included', $benefitsDeduction->tax_included ?? false)" />
                </div>
                <div>
                    <x-form-checkbox title="{{ __('Show on Payslip') }}" name="show_on_payslip"
                        :checked="old('show_on_payslip', $benefitsDeduction->show_on_payslip ?? true)" />
                </div>
                <div class="col-span-2">
                    <x-form-input title="{{ __('Amount') }}" name="amount" type="number" step="0.01"
                        place-holder="{{ __('Amount') }}" :value="old('amount', $benefitsDeduction->amount ?? '')" />
                </div>
            </div>
        </div>
    </div>
</div>