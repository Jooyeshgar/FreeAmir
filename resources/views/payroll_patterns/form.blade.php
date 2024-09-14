<div class="card bg-gray-100 shadow-xl rounded-xl">
    <div class="card-body p-4">
        <x-show-message-bags />

        <div class="grid grid-cols-4 gap-4">
            <x-form-input title="{{ __('Name') }}" name="name" place-holder="{{ __('name') }}"
                value="{{ old('name', $payrollPattern->name ?? '') }}" />
            <x-form-input title="{{ __('Daily Wage') }}" name="daily_wage" place-holder="{{ __('daily_wage') }}"
                value="{{ old('daily_wage', $payrollPattern->daily_wage ?? '') }}" />
            <x-form-input title="{{ __('Overtime Hourly') }}" name="overtime_hourly"
                place-holder="{{ __('overtime_hourly') }}"
                value="{{ old('overtime_hourly', $payrollPattern->overtime_hourly ?? '') }}" />
            <x-form-input title="{{ __('Holiday Work') }}" name="holiday_work" place-holder="{{ __('holiday_work') }}"
                value="{{ old('holiday_work', $payrollPattern->holiday_work ?? '') }}" />
            <x-form-input title="{{ __('Friday Work') }}" name="friday_work" place-holder="{{ __('friday_work') }}"
                value="{{ old('friday_work', $payrollPattern->friday_work ?? '') }}" />
            <x-form-input title="{{ __('Child Allowance') }}" name="child_allowance"
                place-holder="{{ __('child_allowance') }}"
                value="{{ old('child_allowance', $payrollPattern->child_allowance ?? '') }}" />
            <x-form-input title="{{ __('Housing Allowance') }}" name="housing_allowance"
                place-holder="{{ __('housing_allowance') }}"
                value="{{ old('housing_allowance', $payrollPattern->housing_allowance ?? '') }}" />
            <x-form-input title="{{ __('Grocery Allowance') }}" name="grocery_allowance"
                place-holder="{{ __('grocery_allowance') }}"
                value="{{ old('grocery_allowance', $payrollPattern->grocery_allowance ?? '') }}" />
            <x-form-input title="{{ __('Marriage Allowance') }}" name="marriage_allowance"
                place-holder="{{ __('marriage_allowance') }}"
                value="{{ old('marriage_allowance', $payrollPattern->marriage_allowance ?? '') }}" />
            <x-form-input title="{{ __('Insurance Percentage') }}" name="insurance_percentage"
                place-holder="{{ __('insurance_percentage') }}"
                value="{{ old('insurance_percentage', $payrollPattern->insurance_percentage ?? '') }}" />
            <x-form-input title="{{ __('Unemployment Insurance') }}" name="unemployment_insurance"
                place-holder="{{ __('unemployment_insurance') }}"
                value="{{ old('unemployment_insurance', $payrollPattern->unemployment_insurance ?? '') }}" />
            <x-form-input title="{{ __('Employer Share') }}" name="employer_share"
                place-holder="{{ __('employer_share') }}"
                value="{{ old('employer_share', $payrollPattern->employer_share ?? '') }}" />

        </div>
    </div>
</div>