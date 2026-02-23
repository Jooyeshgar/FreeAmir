<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Employee Details') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            <div class="flex items-center justify-between mb-4">
                <h2 class="card-title">
                    {{ $employee->first_name }} {{ $employee->last_name }}
                    <span class="badge badge-outline ms-2">{{ $employee->code }}</span>
                    @if ($employee->is_active)
                        <span class="badge badge-success">{{ __('Active') }}</span>
                    @else
                        <span class="badge badge-error">{{ __('Inactive') }}</span>
                    @endif
                </h2>
                <div class="flex gap-2">
                    @can('hr.employees.edit')
                        <a href="{{ route('employees.edit', $employee) }}" class="btn btn-info btn-sm">
                            {{ __('Edit') }}
                        </a>
                    @endcan
                    <a href="{{ route('employees.index') }}" class="btn btn-ghost btn-sm">
                        {{ __('Back') }}
                    </a>
                </div>
            </div>

            {{-- Identity --}}
            <div class="divider text-sm font-semibold">{{ __('Identity') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <div><span class="font-medium">{{ __('National Code') }}:</span> {{ $employee->national_code ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Passport Number') }}:</span> {{ $employee->passport_number ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Father Name') }}:</span> {{ $employee->father_name ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Nationality') }}:</span> {{ $employee->nationality?->label() ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Gender') }}:</span> {{ $employee->gender?->label() ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Marital Status') }}:</span> {{ $employee->marital_status?->label() ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Children Count') }}:</span> {{ $employee->children_count }}</div>
                <div><span class="font-medium">{{ __('Birth Date') }}:</span> {{ $employee->birth_date?->format('Y-m-d') ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Birth Place') }}:</span> {{ $employee->birth_place ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Duty Status') }}:</span> {{ $employee->duty_status?->label() ?? '—' }}</div>
            </div>

            {{-- Contact --}}
            <div class="divider text-sm font-semibold">{{ __('Contact') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div><span class="font-medium">{{ __('Phone') }}:</span> {{ $employee->phone ?? '—' }}</div>
                <div class="md:col-span-2"><span class="font-medium">{{ __('Address') }}:</span> {{ $employee->address ?? '—' }}</div>
            </div>

            {{-- Insurance --}}
            <div class="divider text-sm font-semibold">{{ __('Insurance') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div><span class="font-medium">{{ __('Insurance Number') }}:</span> {{ $employee->insurance_number ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Insurance Type') }}:</span> {{ $employee->insurance_type?->label() ?? '—' }}</div>
            </div>

            {{-- Banking --}}
            <div class="divider text-sm font-semibold">{{ __('Banking') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div><span class="font-medium">{{ __('Bank Name') }}:</span> {{ $employee->bank_name ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Bank Account') }}:</span> {{ $employee->bank_account ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Card Number') }}:</span> {{ $employee->card_number ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Shaba Number') }}:</span> {{ $employee->shaba_number ?? '—' }}</div>
            </div>

            {{-- Education --}}
            <div class="divider text-sm font-semibold">{{ __('Education') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div><span class="font-medium">{{ __('Education Level') }}:</span> {{ $employee->education_level?->label() ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Field of Study') }}:</span> {{ $employee->field_of_study ?? '—' }}</div>
            </div>

            {{-- Employment --}}
            <div class="divider text-sm font-semibold">{{ __('Employment') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <div><span class="font-medium">{{ __('Employment Type') }}:</span> {{ $employee->employment_type?->label() ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Contract Start Date') }}:</span> {{ $employee->contract_start_date?->format('Y-m-d') ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Contract End Date') }}:</span> {{ $employee->contract_end_date?->format('Y-m-d') ?? '—' }}</div>
            </div>

            {{-- Organization --}}
            <div class="divider text-sm font-semibold">{{ __('Organization') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <div><span class="font-medium">{{ __('Work Site') }}:</span> {{ $employee->workSite?->name ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Org Chart Position') }}:</span> {{ $employee->orgChart?->title ?? '—' }}</div>
                <div><span class="font-medium">{{ __('Contract Framework') }}:</span> {{ $employee->workSiteContract?->name ?? '—' }}</div>
            </div>

        </div>
    </div>
</x-app-layout>
