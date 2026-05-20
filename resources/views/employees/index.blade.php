<x-app-layout :title="__('Employees')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 py-6 px-1">
        <div>
            <h1 class="text-xl font-bold text-base-content">{{ __('Employees') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your organization\'s team members') }}</p>
        </div>
        @can('hr.employees.create')
            <a href="{{ route('hr.employees.create') }}" class="btn btn-primary btn-sm gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Add Employee') }}
            </a>
        @endcan
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 px-1">
        <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-primary opacity-70">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div class="stat-title text-xs">{{ __('Total Employees') }}</div>
            <div class="stat-value text-primary text-3xl">{{ $totalCount }}</div>
        </div>
        <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-success opacity-70">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="stat-title text-xs">{{ __('Active') }}</div>
            <div class="stat-value text-success text-3xl">{{ $activeCount }}</div>
        </div>
        <div class="stat bg-base-100 rounded-xl shadow-sm border border-base-200 py-4">
            <div class="stat-figure text-error opacity-70">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="stat-title text-xs">{{ __('Inactive') }}</div>
            <div class="stat-value text-error text-3xl">{{ $inactiveCount }}</div>
        </div>
    </div>

    {{-- Main Card --}}
    <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
        <div class="card-body p-0">

            {{-- Filters --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                <form action="{{ route('hr.employees.index') }}" method="GET" class="flex flex-wrap gap-2">
                    <div class="join">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="{{ __('Search by name, code or national code') }}"
                            class="input input-sm join-item w-72" />
                        <button type="submit" class="btn btn-sm btn-neutral join-item">{{ __('Search') }}</button>
                    </div>
                    <select name="is_active" class="select select-sm" onchange="this.form.submit()">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>
                    @if(request()->hasAny(['search', 'is_active']))
                        <a href="{{ route('hr.employees.index') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                    @endif
                </form>
                <span class="text-sm text-base-content/40 hidden sm:block">
                    {{ $employees->total() }} {{ __('records') }}
                </span>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="text-base-content/60 text-xs uppercase tracking-wide bg-base-200/40">
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('National Code') }}</th>
                            <th>{{ __('Employment Type') }}</th>
                            <th>{{ __('Work Site') }}</th>
                            <th>{{ __('Organization Unit') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr class="hover:bg-base-200/30 transition-colors border-b border-base-200/60 last:border-0">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar placeholder">
                                            <div class="bg-neutral/10 text-neutral rounded-full w-9 h-9 flex items-center justify-center">
                                                <span class="text-sm font-semibold leading-none">
                                                    {{ mb_substr($employee->first_name, 0, 1) }}{{ mb_substr($employee->last_name, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <span class="font-medium text-sm">{{ $employee->first_name }} {{ $employee->last_name }}</span>
                                    </div>
                                </td>
                                <td class="text-sm text-base-content/60 font-mono">{{ $employee->code }}</td>
                                <td class="text-sm text-base-content/60">{{ $employee->national_code ?? '—' }}</td>
                                <td class="text-sm">{{ $employee->employment_type?->label() ?? '—' }}</td>
                                <td class="text-sm">{{ $employee->workSite?->name ?? '—' }}</td>
                                <td>{{ $employee->organizationUnit?->name ?? '—' }}</td>
                                <td>
                                    @if ($employee->is_active)
                                        <span class="badge badge-success badge-sm gap-1 font-medium">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                                            {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="badge badge-error badge-sm gap-1 font-medium">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                                            {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-center gap-1">
                                        @can('hr.employees.show')
                                            <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-xs btn-ghost">
                                                {{ __('View') }}
                                            </a>
                                        @endcan
                                        @can('hr.employees.edit')
                                            <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-xs btn-info">
                                                {{ __('Edit') }}
                                            </a>
                                        @endcan
                                        @can('hr.employees.delete')
                                            <form action="{{ route('hr.employees.destroy', $employee) }}" method="POST" class="inline-block"
                                                onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-error">
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="flex flex-col items-center justify-center py-16 text-base-content/30">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <p class="text-base font-medium">{{ __('No employees found.') }}</p>
                                        <p class="text-sm mt-1 text-base-content/25">{{ __('Try adjusting your search filters.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($employees->hasPages())
                <div class="px-5 py-4 border-t border-base-200">
                    {!! $employees->links() !!}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
