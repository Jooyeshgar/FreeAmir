<div class="grid grid-cols-2 gap-6">

    <div class="col-span-2 md:col-span-1">
        <x-select name="employee_id" id="employee_id" title="{{ __('Employee') }}" :selected="old('employee_id', request('employee') ?? isset($salaryDecree) ? $salaryDecree->employee_id : '')" :options="$employees
            ->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])
            ->prepend('— ' . __('Select Employee') . ' —', '')
            ->all()" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Decree Name') }}" :value="old('name', $salaryDecree->name ?? '')" placeholder="{{ __('e.g. Decree-1403-001') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-date-picker name="start_date" id="start_date" title="{{ __('Start Date') }}" :value="old('start_date', isset($salaryDecree) ? toEnglish(formatDate($salaryDecree->start_date)) : '')" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-date-picker name="end_date" id="end_date" title="{{ __('End Date') }}" :value="old('end_date', isset($salaryDecree) && $salaryDecree->end_date ? toEnglish(formatDate($salaryDecree->end_date)) : '')" />
        <p class="text-gray-400 text-xs mt-1">{{ __('Leave empty if the decree is currently active.') }}</p>
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="daily_wage" id="daily_wage" type="number" title="{{ __('Daily Wage') }}" :value="old('daily_wage', $salaryDecree->daily_wage ?? '')" placeholder="0" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-checkbox name="is_active" id="is_active" title="{{ __('Active') }}" value="1" :checked="old('is_active', $salaryDecree->is_active ?? true)" />
    </div>

    <div class="col-span-2">
        <x-textarea name="description" id="description" title="{{ __('Description') }}" :value="old('description', $salaryDecree->description ?? '')" placeholder="{{ __('Optional notes about this decree') }}" />
    </div>

</div>

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
                            <select :name="`benefits[${index}][element_id]`" class="select select-bordered w-full" x-model="benefit.element_id"
                                @change="fillDefault(index)" required>
                                <option value="">— {{ __('Select Element') }} —</option>
                                @foreach ($payrollElements as $element)
                                    <option value="{{ $element->id }}">{{ $element->title }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" :name="`benefits[${index}][value]`" x-model="benefit.value" class="input input-bordered w-full" placeholder="0"
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
                elementDefaults: @json($payrollElements->keyBy('id')->map(fn($e) => $e->default_amount)),

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

                fillDefault(index) {
                    const id = this.benefits[index].element_id;
                    if (id && this.elementDefaults[id] !== undefined) {
                        this.benefits[index].value = this.elementDefaults[id];
                    }
                },
            };
        }
    </script>
@endpush
