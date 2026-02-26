<div class="grid grid-cols-2 gap-6">

    {{-- Employee --}}
    <div class="col-span-2 md:col-span-1">
        <label for="employee_id" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Employee') }} <span class="text-red-500">*</span>
        </label>
        <select name="employee_id" id="employee_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            required>
            <option value="">— {{ __('Select Employee') }} —</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" {{ old('employee_id', $salaryDecree->employee_id ?? '') == $employee->id ? 'selected' : '' }}>
                    {{ $employee->first_name }} {{ $employee->last_name }}
                </option>
            @endforeach
        </select>
        @error('employee_id')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Org Chart Position --}}
    <div class="col-span-2 md:col-span-1">
        <label for="org_chart_id" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Position') }} <span class="text-red-500">*</span>
        </label>
        <select name="org_chart_id" id="org_chart_id"
            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" required>
            <option value="">— {{ __('Select Position') }} —</option>
            @foreach ($orgCharts as $orgChart)
                <option value="{{ $orgChart->id }}" {{ old('org_chart_id', $salaryDecree->org_chart_id ?? '') == $orgChart->id ? 'selected' : '' }}>
                    {{ $orgChart->title }}
                </option>
            @endforeach
        </select>
        @error('org_chart_id')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Decree Name --}}
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Decree Name') }}" :value="old('name', $salaryDecree->name ?? '')" placeholder="{{ __('e.g. Decree-1403-001') }}" />
    </div>

    {{-- Contract Type --}}
    <div class="col-span-2 md:col-span-1">
        <label for="contract_type" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Contract Type') }}
        </label>
        <select name="contract_type" id="contract_type"
            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            <option value="">— {{ __('Select Contract Type') }} —</option>
            @foreach (['full_time' => __('Full Time'), 'part_time' => __('Part Time'), 'hourly' => __('Hourly'), 'shift' => __('Shift')] as $value => $label)
                <option value="{{ $value }}" {{ old('contract_type', $salaryDecree->contract_type ?? '') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('contract_type')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Start Date --}}
    <div class="col-span-2 md:col-span-1">
        <x-date-picker name="start_date" id="start_date" title="{{ __('Start Date') }}" :value="old('start_date', isset($salaryDecree) ? $salaryDecree->start_date->format('Y-m-d') : '')" required />
    </div>

    {{-- End Date --}}
    <div class="col-span-2 md:col-span-1">
        <x-date-picker name="end_date" id="end_date" title="{{ __('End Date') }}" :value="old('end_date', isset($salaryDecree) && $salaryDecree->end_date ? $salaryDecree->end_date->format('Y-m-d') : '')" />
        <p class="text-gray-400 text-xs mt-1">{{ __('Leave empty if the decree is currently active.') }}</p>
    </div>

    {{-- Daily Wage --}}
    <div class="col-span-2 md:col-span-1">
        <x-input name="daily_wage" id="daily_wage" type="number" title="{{ __('Daily Wage') }}" :value="old('daily_wage', $salaryDecree->daily_wage ?? '')" placeholder="0" />
    </div>

    {{-- Is Active --}}
    <div class="col-span-2 md:col-span-1 flex items-center gap-3 mt-4">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" name="is_active" id="is_active" value="1" class="checkbox checkbox-primary"
            {{ old('is_active', $salaryDecree->is_active ?? true) ? 'checked' : '' }} />
        <label for="is_active" class="text-sm font-medium text-gray-700">
            {{ __('Active') }}
        </label>
        @error('is_active')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Description --}}
    <div class="col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Description') }}
        </label>
        <textarea name="description" id="description" rows="3"
            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            placeholder="{{ __('Optional notes about this decree') }}">{{ old('description', $salaryDecree->description ?? '') }}</textarea>
        @error('description')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

</div>

{{-- Payroll Benefits --}}
<div class="mt-8" x-data="benefitsManager()" x-init="init()">
    <h3 class="text-base font-semibold text-gray-700 mb-3">{{ __('Payroll Benefits') }}</h3>

    <div class="overflow-x-auto">
        <table class="table w-full">
            <thead>
                <tr>
                    <th>{{ __('Payroll Element') }}</th>
                    <th>{{ __('Value') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="benefits-body">
                <template x-for="(benefit, index) in benefits" :key="index">
                    <tr>
                        <td>
                            <select :name="`benefits[${index}][element_id]`"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                x-model="benefit.element_id" required>
                                <option value="">— {{ __('Select Element') }} —</option>
                                @foreach ($payrollElements as $element)
                                    <option value="{{ $element->id }}">{{ $element->title }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" :name="`benefits[${index}][value]`" x-model="benefit.value"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" placeholder="0"
                                min="0" required />
                        </td>
                        <td>
                            <button type="button" @click="remove(index)" class="btn btn-sm btn-error btn-outline">
                                {{ __('Remove') }}
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <button type="button" @click="add()" class="btn btn-sm btn-outline btn-primary mt-3">
        + {{ __('Add Benefit') }}
    </button>
</div>

@php
    $initialBenefits = collect(
        old(
            'benefits',
            isset($salaryDecree)
                ? $salaryDecree->benefits->map(
                    fn($b) => [
                        'element_id' => $b->element_id,
                        'value' => $b->element_value,
                    ],
                )
                : collect(),
        ),
    )
        ->values()
        ->all();
@endphp

@push('scripts')
    <script>
        function benefitsManager() {
            return {
                benefits: @json($initialBenefits),

                init() {
                    if (this.benefits.length === 0) {
                        this.add();
                    }
                },

                add() {
                    this.benefits.push({
                        element_id: '',
                        value: ''
                    });
                },

                remove(index) {
                    this.benefits.splice(index, 1);
                },
            };
        }
    </script>
@endpush
