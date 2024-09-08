<div class="card bg-gray-100 shadow-xl rounded-xl">
    <div class="card-body p-4">
        <x-show-message-bags />
        <div class="grid grid-cols-1 gap-4">
            <div class="grid grid-cols-4 gap-4">
                <!-- Existing Fields -->
                <x-form-input title="{{ __('Name') }}" name="name" place-holder="{{ __('Name') }}"
                    value="{{ old('name', $salarySlip->name ?? '') }}" />
                <x-form-input title="{{ __('Daily Wage') }}" name="daily_wage" place-holder="{{ __('Daily Wage') }}"
                    value="{{ old('daily_wage', $salarySlip->daily_wage ?? '') }}" />
                <x-form-input title="{{ __('Overtime Hourly') }}" name="overtime_hourly"
                    place-holder="{{ __('Overtime Hourly') }}"
                    value="{{ old('overtime_hourly', $salarySlip->overtime_hourly ?? '') }}" />
                <x-form-input title="{{ __('Holiday Work') }}" name="holiday_work"
                    place-holder="{{ __('Holiday Work') }}"
                    value="{{ old('holiday_work', $salarySlip->holiday_work ?? '') }}" />
                <x-form-input title="{{ __('Friday Work') }}" name="friday_work" place-holder="{{ __('Friday Work') }}"
                    value="{{ old('friday_work', $salarySlip->friday_work ?? '') }}" />
                <x-form-input title="{{ __('Child Allowance') }}" name="child_allowance"
                    place-holder="{{ __('Child Allowance') }}"
                    value="{{ old('child_allowance', $salarySlip->child_allowance ?? '') }}" />
                <x-form-input title="{{ __('Housing Allowance') }}" name="housing_allowance"
                    place-holder="{{ __('Housing Allowance') }}"
                    value="{{ old('housing_allowance', $salarySlip->housing_allowance ?? '') }}" />
                <x-form-input title="{{ __('Grocery Allowance') }}" name="grocery_allowance"
                    place-holder="{{ __('Grocery Allowance') }}"
                    value="{{ old('grocery_allowance', $salarySlip->grocery_allowance ?? '') }}" />
                <x-form-input title="{{ __('Marriage Allowance') }}" name="marriage_allowance"
                    place-holder="{{ __('Marriage Allowance') }}"
                    value="{{ old('marriage_allowance', $salarySlip->marriage_allowance ?? '') }}" />
                <x-form-input title="{{ __('Insurance Percentage') }}" name="insurance_percentage"
                    place-holder="{{ __('Insurance Percentage') }}"
                    value="{{ old('insurance_percentage', $salarySlip->insurance_percentage ?? '') }}" />
                <x-form-input title="{{ __('Unemployment Insurance') }}" name="unemployment_insurance"
                    place-holder="{{ __('Unemployment Insurance') }}"
                    value="{{ old('unemployment_insurance', $salarySlip->unemployment_insurance ?? '') }}" />
                <x-form-input title="{{ __('Employer Share') }}" name="employer_share"
                    place-holder="{{ __('Employer Share') }}"
                    value="{{ old('employer_share', $salarySlip->employer_share ?? '') }}" />

                <!-- Payroll Pattern Selection -->
                <x-form-select title="{{ __('Payroll Pattern') }}" name="payroll_pattern_id"
                    :options="$payrollPatterns->pluck('name', 'id')" :selected="old('payroll_pattern_id', $salarySlip->payroll_pattern_id ?? '')" />

            </div>
            <hr>

            <div class="grid grid-cols-1 gap-4">

                <div id="BenefitsDeductionsList" class=" grid grid-cols-1 gap-4">

                    @if(count($salarySlip->benefitsDeductions ?? []) || old('benefits_deductions_id'))
                        @foreach ((old('benefits_deductions_id') ?? $salarySlip->benefitsDeductions) as $index => $oldSelectedId)
                            <div class="BenefitsDeductions flex items-center">

                                <svg onclick="Array.from(document.getElementsByClassName('BenefitsDeductions')).length>1?this.parentNode.remove():''"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor"
                                    class="px-2 size-8 rounded-md  h-11 flex justify-center items-center text-center  bg-red-500 hover:bg-red-700 text-white font-bold rounded removeTransaction text-center">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0">
                                    </path>
                                </svg>

                                <div class="flex-1">
                                    <x-form-select :title="false" class="flex-1" name="benefits_deductions_id[]"
                                        :options="$benefitsDeductions->pluck('name', 'id')"
                                        :selected="old('benefits_deductions_id')[$index] ?? $salarySlip->benefitsDeductions[$index]->id" />
                                </div>

                                <div class="flex-1">

                                    <x-form-input type="number" :title="false" class="flex-1"
                                        name="benefits_deductions_amount[]" place-holder="{{ __('amount') }}"
                                        :value="old('benefits_deductions_amount')[$index] ?? $salarySlip->benefitsDeductions[$index]->pivot->amount" />
                                </div>

                            </div>
                        @endforeach
                    @else
                        <div class="BenefitsDeductions flex items-center">

                            <svg onclick="Array.from(document.getElementsByClassName('BenefitsDeductions')).length>1?this.parentNode.remove():''"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor"
                                class="px-2 size-8 rounded-md  h-11 flex justify-center items-center text-center  bg-red-500 hover:bg-red-700 text-white font-bold rounded removeTransaction text-center">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0">
                                </path>
                            </svg>
                            <div class="flex-1">
                                <x-form-select :title="false" class="flex-1" name="benefits_deductions_id[]"
                                    :options="$benefitsDeductions->pluck('name', 'id')" :selected="''" />
                            </div>
                            <div class="flex-1">

                                <x-form-input type="number" :title="false" class="flex-1"
                                    name="benefits_deductions_amount[]" place-holder="{{ __('amount') }}" value="" />
                            </div>
                        </div>
                    @endif

                </div>
                <div>
                    <div class="flex justify-content gap-4 align-center">
                        <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active"
                            id="addBenefitsDeductions">
                            <span class="text-2xl">+</span>
                            افزودن مزایا/کسورات
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
    </di>
</div>
<script>
    var benefitsDeductions = JSON.parse(`<?php echo $benefitsDeductions; ?>`)
    document.getElementById('addBenefitsDeductions').addEventListener('click', function () {
        var transactionsDiv = document.getElementById('BenefitsDeductionsList');
        var transactionDivs = transactionsDiv.getElementsByClassName('BenefitsDeductions');
        var lastTransactionDiv = transactionDivs[transactionDivs.length - 1];
        var newTransactionDiv = lastTransactionDiv.cloneNode(true);
        // Update the index in the name attribute


        var inputs = newTransactionDiv.getElementsByTagName('input');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].name = inputs[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
            inputs[i].value = ''
        }


        // Add the remove button event listener


        // Append the new transaction div to the transactions div
        transactionsDiv.appendChild(newTransactionDiv);
    });

</script>