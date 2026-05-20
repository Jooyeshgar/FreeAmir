<x-app-layout :title="__('Employees')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Employees') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your organization\'s team members') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2" dir="ltr">
            @can('hr.employees.create')
                <a href="{{ route('hr.employees.create') }}" class="btn btn-primary btn-sm gap-1.5" dir="rtl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Employee') }}
                </a>
            @endcan

            @can('hr.employees.export')
                <a href="{{ route('hr.employees.export', request()->only(['search', 'is_active', 'work_site_id', 'contract_framework_id'])) }}"
                    class="btn btn-sm btn-outline gap-1.5" dir="rtl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
                    </svg>
                    {{ __('CSV Export') }}
                </a>
            @endcan

            <form action="{{ route('hr.employees.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                @if (request()->filled('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                @if (request()->filled('is_active'))
                    <input type="hidden" name="is_active" value="{{ request('is_active') }}">
                @endif
                <select name="contract_framework_id" class="select select-sm w-36" dir="rtl" onchange="this.form.submit()">
                    <option value="">{{ __('All Contracts') }}</option>
                    @foreach ($workSiteContracts as $contract)
                        <option value="{{ $contract->id }}" {{ (string) request('contract_framework_id') === (string) $contract->id ? 'selected' : '' }}>
                            {{ $contract->name }}
                        </option>
                    @endforeach
                </select>
                <select name="work_site_id" class="select select-sm w-36" dir="rtl" onchange="this.form.submit()">
                    <option value="">{{ __('All Units') }}</option>
                    @foreach ($workSites as $workSite)
                        <option value="{{ $workSite->id }}" {{ (string) request('work_site_id') === (string) $workSite->id ? 'selected' : '' }}>
                            {{ $workSite->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="mb-6 px-1">
        <x-stat-strip :items="[
            [
                'title' => __('Total Employees'),
                'value' => convertToFarsi($totalCount),
                'description' => __('Registered in system'),
                'icon' => 'users',
                'tone' => 'indigo',
            ],
            [
                'title' => __('Active'),
                'value' => convertToFarsi($activeCount),
                'description' => __('Currently working'),
                'icon' => 'check',
                'tone' => 'green',
            ],
            [
                'title' => __('Full Time'),
                'value' => convertToFarsi($fullTimeCount),
                'description' => __('Official contract'),
                'icon' => 'briefcase',
                'tone' => 'sky',
            ],
            [
                'title' => __('Remote / Part Time'),
                'value' => convertToFarsi($flexibleCount),
                'description' => __('Flexible'),
                'icon' => 'cup',
                'tone' => 'cyan',
            ],
            [
                'title' => __('New Hires'),
                'value' => convertToFarsi($newHiresCount),
                'description' => __('In last 30 days'),
                'icon' => 'plus',
                'tone' => 'amber',
            ],
            [
                'title' => __('Waiting for Salary Decree'),
                'value' => convertToFarsi($withoutSalaryDecreeCount),
                'description' => __('Needs review'),
                'icon' => 'document',
                'tone' => 'red',
            ],
        ]" />
    </div>

    {{-- Employee List --}}
    <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
        <div class="card-body p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Employee List') }}</h2>
                    <span class="badge badge-ghost">
                        {{ convertToFarsi($employees->total()) }} {{ __('records') }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2" dir="ltr">
                    <form action="{{ route('hr.employees.index') }}" method="GET" class="join">
                        @if (request()->filled('work_site_id'))
                            <input type="hidden" name="work_site_id" value="{{ request('work_site_id') }}">
                        @endif
                        @if (request()->filled('contract_framework_id'))
                            <input type="hidden" name="contract_framework_id" value="{{ request('contract_framework_id') }}">
                        @endif
                        @if (request()->filled('is_active'))
                            <input type="hidden" name="is_active" value="{{ request('is_active') }}">
                        @endif
                        <label class="input input-sm join-item flex w-64 max-w-full items-center gap-2 bg-base-100" dir="rtl">
                            <input type="search" name="search" value="{{ request('search') }}" class="grow"
                                placeholder="{{ __('Search by name, code or national code') }}" />
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                            </svg>
                        </label>
                    </form>
                </div>
            </div>

            <div class="p-4 sm:p-5">
                @if ($employees->count())
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($employees as $employee)
                            @php
                                $avatarTones = [
                                    'bg-blue-600 text-white',
                                    'bg-emerald-600 text-white',
                                    'bg-amber-500 text-white',
                                    'bg-rose-600 text-white',
                                    'bg-violet-600 text-white',
                                    'bg-cyan-600 text-white',
                                ];
                                $avatarTone = $avatarTones[$employee->id % count($avatarTones)];
                                $hasExpiredContract = $employee->is_active && $employee->contract_end_date && $employee->contract_end_date->lt(today());
                                $hasNoSalaryDecree = (int) $employee->salary_decrees_count === 0;
                                $hasInactiveSalaryDecree = !$hasNoSalaryDecree && (int) $employee->active_salary_decrees_count === 0;
                                $needsAttention = $hasExpiredContract || $hasNoSalaryDecree || $hasInactiveSalaryDecree;
                            @endphp
                            <div
                                class="card rounded-lg border {{ $needsAttention ? 'border-error bg-error/5' : 'border-base-200 bg-base-100 dark:bg-base-200/40' }} shadow-sm transition hover:border-primary/30 hover:shadow-md">
                                <div class="card-body gap-4 p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar placeholder shrink-0">
                                            <div class="{{ $avatarTone }} flex h-14 w-14 items-center justify-center rounded-2xl text-center">
                                                <span class="text-lg font-bold leading-none">
                                                    {{ mb_substr($employee->first_name, 0, 1) }}{{ mb_substr($employee->last_name, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="min-w-0 space-y-1">
                                            <h3 class="truncate text-base font-bold text-base-content">{{ $employee->first_name }} {{ $employee->last_name }}</h3>
                                            <p class="truncate text-sm text-base-content/60">{{ $employee->orgChart?->title ?? __('No position') }}</p>
                                        </div>
                                    </div>

                                    <div class="space-y-2 text-sm text-base-content/65">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/35" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m4 0h4" />
                                            </svg>
                                            <span>{{ $employee->workSite?->name ?? __('No work site') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/35" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                            </svg>
                                            <span>{{ $employee->organizationUnit?->name ?? __('No unit') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/35" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-7 4h8M7 4h10a2 2 0 0 1 2 2v14l-4-2-3 2-3-2-4 2V6a2 2 0 0 1 2-2Z" />
                                            </svg>
                                            <span>{{ $employee->code }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/35" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3M5 11h14M7 21h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" />
                                            </svg>
                                            <span>{{ $employee->contract_start_date ? formatDate($employee->contract_start_date) : __('No start date') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge {{ $employee->is_active ? 'badge-success' : 'badge-error' }} badge-sm">
                                            {{ $employee->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                        <span class="badge badge-info badge-outline badge-sm">
                                            {{ $employee->employment_type?->label() ?? __('No type') }}
                                        </span>
                                        @if ($hasExpiredContract)
                                            <span class="badge badge-error badge-sm">
                                                {{ __('Expired contract') }}
                                            </span>
                                        @endif
                                        @if ($hasNoSalaryDecree)
                                            <span class="badge badge-error badge-outline badge-sm">
                                                {{ __('No salary decree') }}
                                            </span>
                                        @elseif ($hasInactiveSalaryDecree)
                                            <span class="badge badge-warning badge-outline badge-sm">
                                                {{ __('Salary not approved') }}
                                            </span>
                                        @else
                                            <span class="badge badge-success badge-outline badge-sm">
                                                {{ __('Salary approved') }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="card-actions items-center justify-between border-t border-base-200 pt-3">
                                        @can('hr.employees.show')
                                            <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-xs btn-ghost gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                {{ __('View') }}
                                            </a>
                                        @endcan
                                        <div class="flex items-center gap-1 text-base-content/45">
                                            @if ($employee->phone)
                                                <a href="tel:{{ $employee->phone }}" class="btn btn-xs btn-ghost btn-square" title="{{ __('Phone') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 0 0-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97a1.125 1.125 0 0 0 .417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                                    </svg>
                                                </a>
                                            @endif
                                            @can('hr.employees.edit')
                                                <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-xs btn-ghost btn-square"
                                                    title="{{ __('Edit') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.582 18.07a4.5 4.5 0 0 1-1.897 1.13L3 20.25l1.05-3.685a4.5 4.5 0 0 1 1.13-1.897l11.682-11.681Z" />
                                                    </svg>
                                                </a>
                                            @endcan
                                            @can('hr.employees.delete')
                                                <form action="{{ route('hr.employees.destroy', $employee) }}" method="POST"
                                                    onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-ghost btn-square text-error" title="{{ __('Delete') }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.827L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-16 text-base-content/35">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <p class="text-base font-medium">{{ __('No employees found.') }}</p>
                        <p class="mt-1 text-sm text-base-content/30">{{ __('Try adjusting your search filters.') }}</p>
                    </div>
                @endif
            </div>

            {{-- Pagination --}}
            @if ($employees->hasPages())
                <div class="px-5 py-4 border-t border-base-200">
                    {!! $employees->links() !!}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
