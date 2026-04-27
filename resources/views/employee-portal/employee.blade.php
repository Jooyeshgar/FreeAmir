<x-app-layout title="{{ __('My Information') }}">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">

        {{-- Header --}}
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ $employee->first_name }} {{ $employee->last_name }}
                    </h2>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @if ($employee->code)
                            <span class="badge badge-lg badge-accent gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                </svg>
                                {{ $employee->code }}
                            </span>
                        @endif

                        @if ($employee->workSite)
                            <div class="badge badge-lg badge-primary gap-2 hover:brightness-110 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                {{ $employee->workSite->name }}
                            </div>
                        @endif

                        @if ($employee->orgChart)
                            <div class="badge badge-lg badge-secondary gap-2 hover:brightness-110 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                                {{ $employee->orgChart->title }}
                            </div>
                        @endif

                        @if ($employee->user)
                            <div class="badge badge-lg badge-info gap-2 hover:brightness-110 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ $employee->user->name }}
                            </div>
                        @endif
                        <span class="badge badge-lg badge-emerald gap-2">
                            {{ formatMinutesAsTime($employee->leave_remain) }} {{ __('Remaining Paid Leave') }}
                        </span>
                        @if ($employee->device_id)
                            <span class="badge badge-lg badge-neutral gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('Device ID') }} {{ $employee->device_id }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 shrink-0">
                    <a href="{{ route('employee-portal.change-employee-information') }}" class="btn btn-info btn-sm gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        {{ __('Edit') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">

            {{-- Identity --}}
            <div class="divider text-lg font-semibold">{{ __('Identity') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('National Code') }}</div>
                    <div class="font-semibold">{{ $employee->national_code ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Passport Number') }}</div>
                    <div class="font-semibold">{{ $employee->passport_number ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Father Name') }}</div>
                    <div class="font-semibold">{{ $employee->father_name ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Nationality') }}</div>
                    <div class="font-semibold">{{ $employee->nationality?->label() ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Gender') }}</div>
                    <div class="font-semibold">{{ $employee->gender?->label() ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Marital Status') }}</div>
                    <div class="font-semibold">{{ $employee->marital_status?->label() ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Children Count') }}</div>
                    <div class="font-semibold">{{ $employee->children_count }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Birth Date') }}</div>
                    <div class="font-semibold">{{ $employee->birth_date ? formatDate($employee->birth_date) : '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Birth Place') }}</div>
                    <div class="font-semibold">{{ $employee->birth_place ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Duty Status') }}</div>
                    <div class="font-semibold">{{ $employee->duty_status?->label() ?? '—' }}</div>
                </div>
            </div>

            {{-- Organization --}}
            <div class="divider text-lg font-semibold">{{ __('Organization') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Work Site') }}</div>
                    @if ($employee->workSite)
                        <p class="font-semibold">{{ $employee->workSite->name }}</p>
                    @else
                        <p class="font-semibold">—</p>
                    @endif
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Org Chart Position') }}</div>
                    @if ($employee->orgChart)
                        <p class="font-semibold">{{ $employee->orgChart->title }}</p>
                    @else
                        <p class="font-semibold">—</p>
                    @endif
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Contract Framework') }}</div>
                    @if ($employee->workSiteContract)
                        <p class="font-semibold">{{ $employee->workSiteContract->name }}</p>
                    @else
                        <p class="font-semibold">—</p>
                    @endif
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Work Shift') }}</div>
                    <p class="font-semibold">
                        @php
                            $shift = $employee?->workShift;
                            $shiftStart = $shift ? convertToFarsi(substr($shift->start_time, 0, 5)) : \App\Services\AttendanceService::DEFAULT_SHIFT_START;
                            $shiftEnd = $shift ? convertToFarsi(substr($shift->end_time, 0, 5)) : \App\Services\AttendanceService::DEFAULT_SHIFT_END;
                        @endphp
                        @if ($employee->workShift)
                            <span>{{ $employee->workShift->name }}</span>
                            ({{ $shiftStart }} &ndash; {{ $shiftEnd }})
                        @else
                            {{ $shiftStart }} &ndash; {{ $shiftEnd }}
                        @endif
                    </p>
                </div>
            </div>

            {{-- Contact --}}
            <div class="divider text-lg font-semibold">{{ __('Contact') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Phone') }}</div>
                    @if ($employee->phone)
                        <p  class="font-semibold">{{ $employee->phone }}</p>
                    @else
                        <p class="font-semibold">—</p>
                    @endif
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3 md:col-span-2">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Address') }}</div>
                    <p class="font-semibold">{{ $employee->address ?? '—' }}</p>
                </div>
            </div>

            {{-- Insurance --}}
            <div class="divider text-lg font-semibold">{{ __('Insurance') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Insurance Number') }}</div>
                    <div class="font-semibold">{{ $employee->insurance_number ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Insurance Type') }}</div>
                    <div class="font-semibold">{{ $employee->insurance_type?->label() ?? '—' }}</div>
                </div>
            </div>

            {{-- Banking --}}
            <div class="divider text-lg font-semibold">{{ __('Banking') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Bank Name') }}</div>
                    <div class="font-semibold">{{ $employee->bank_name ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Bank Account') }}</div>
                    <div class="font-semibold font-mono tracking-wide">{{ $employee->bank_account ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Card Number') }}</div>
                    <div class="font-semibold font-mono tracking-widest">{{ $employee->card_number ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Shaba Number') }}</div>
                    <div class="font-semibold font-mono tracking-wide">{{ $employee->shaba_number ?? '—' }}</div>
                </div>
            </div>

            {{-- Education --}}
            <div class="divider text-lg font-semibold">{{ __('Education') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Education Level') }}</div>
                    <div class="font-semibold">{{ $employee->education_level?->label() ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Field of Study') }}</div>
                    <div class="font-semibold">{{ $employee->field_of_study ?? '—' }}</div>
                </div>
            </div>

            {{-- Employment --}}
            <div class="divider text-lg font-semibold">{{ __('Employment') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Employment Type') }}</div>
                    <div class="font-semibold">{{ $employee->employment_type?->label() ?? '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Contract Start Date') }}</div>
                    <div class="font-semibold">{{ $employee->contract_start_date ? formatDate($employee->contract_start_date) : '—' }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('Contract End Date') }}</div>
                    <div class="font-semibold {{ $employee->contract_end_date && $employee->contract_end_date->isPast() ? 'text-error' : '' }}">
                        {{ $employee->contract_end_date ? formatDate($employee->contract_end_date) : '—' }}
                    </div>
                </div>
            </div>

            {{-- Account User --}}
            @if ($employee->user)
                <div class="divider text-lg font-semibold">{{ __('System Account') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('User Account Name') }}</div>
                    <div class="font-semibold">{{ $employee->user->name }}</div>
                </div>
                <div class="bg-base-200 rounded-lg px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">{{ __('User Account Email') }}</div>
                    <div class="font-semibold">{{ $employee->user->email }}</div>
                </div>
            </div>
            @endif

            {{-- Actions --}}
            <div class="card-actions justify-between mt-8">
                <a href="{{ url()->previous() }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <a href="{{ route('employee-portal.change-employee-information') }}" class="btn btn-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    {{ __('Edit') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
