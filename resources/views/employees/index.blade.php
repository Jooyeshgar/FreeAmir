<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Employees') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Filters --}}
            <div class="flex flex-wrap items-end justify-between gap-3">
                <form action="{{ route('employees.index') }}" method="GET" class="flex flex-wrap items-end gap-2">

                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name, code or national code') }}"
                        class="input input-bordered input-sm w-64" />

                    <select name="is_active" class="select select-bordered select-sm">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                    <a href="{{ route('employees.index') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                </form>

                @can('hr.employees.create')
                    <a href="{{ route('employees.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Employee') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </a>
                @endcan
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto mt-4">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Full Name') }}</th>
                            <th>{{ __('National Code') }}</th>
                            <th>{{ __('Employment Type') }}</th>
                            <th>{{ __('Work Site') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>{{ $employee->code }}</td>
                                <td>{{ $employee->first_name }} {{ $employee->last_name }}</td>
                                <td>{{ $employee->national_code ?? '—' }}</td>
                                <td>{{ $employee->employment_type?->label() ?? '—' }}</td>
                                <td>{{ $employee->workSite?->name ?? '—' }}</td>
                                <td>
                                    @if ($employee->is_active)
                                        <span class="badge badge-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge badge-error">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="flex gap-2">
                                    @can('hr.employees.show')
                                        <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-ghost">
                                            {{ __('View') }}
                                        </a>
                                    @endcan
                                    @can('hr.employees.edit')
                                        <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-info">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                    @can('hr.employees.delete')
                                        <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="inline-block"
                                            onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-gray-500">
                                    {{ __('No employees found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $employees->links() !!}

        </div>
    </div>
</x-app-layout>
