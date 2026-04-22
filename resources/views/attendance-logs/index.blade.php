<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Attendance Logs') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Filter bar --}}
            <form action="{{ route('attendance.attendance-logs.index') }}" method="GET" class="flex flex-wrap items-end gap-3 mb-2">

                <div class="w-48">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Employee') }}</span>
                        </div>
                        <select name="employee_id" class="select select-bordered select-sm">
                            <option value="">{{ __('All Employees') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->first_name }} {{ $employee->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="w-36">
                    <x-date-picker name="date_from" id="date_from" title="{{ __('From Date') }}" :value="request('date_from')" />
                </div>

                <div class="w-36">
                    <x-date-picker name="date_to" id="date_to" title="{{ __('To Date') }}" :value="request('date_to')" />
                </div>

                <div class="w-36">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Entry Type') }}</span>
                        </div>
                        <select name="is_manual" class="select select-bordered select-sm">
                            <option value="">{{ __('All') }}</option>
                            <option value="1" {{ request('is_manual') === '1' ? 'selected' : '' }}>
                                {{ __('Manual') }}
                            </option>
                            <option value="0" {{ request('is_manual') === '0' ? 'selected' : '' }}>
                                {{ __('Automatic') }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="flex gap-2 items-end pb-1">
                    <button type="submit" class="btn btn-sm btn-primary">
                        {{ __('Search') }}
                    </button>
                    <a href="{{ route('attendance.attendance-logs.index') }}" class="btn btn-sm btn-ghost">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>

            {{-- Table header row --}}
            <div class="flex items-center justify-end gap-2">
                @can('attendance.attendance-logs.create')
                    <a href="{{ route('attendance.attendance-logs.import') }}" class="btn btn-sm btn-outline btn-secondary" title="{{ __('Import Attendance Logs') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                        </svg>
                        {{ __('Import') }}
                    </a>
                    <a href="{{ route('attendance.attendance-logs.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Attendance Log') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </a>
                @endcan
            </div>

            <div class="overflow-x-auto">
                <table class="table w-full mt-2">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Entry Time') }}</th>
                            <th>{{ __('Exit Time') }}</th>
                            <th>{{ __('Manual') }}</th>
                            <th>{{ __('Monthly Attendance') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendanceLogs as $log)
                            <tr>
                                <td>
                                    {{ $log->employee?->first_name }} {{ $log->employee?->last_name }}
                                </td>
                                <td>{{ formatDate($log->log_date) }}</td>
                                <td>{{ $log->entry_time ? convertToFarsi($log->entry_time) : '—' }}</td>
                                <td>{{ $log->exit_time ? convertToFarsi($log->exit_time) : '—' }}</td>
                                <td>
                                    @if ($log->is_manual)
                                        <span class="badge badge-warning badge-sm">{{ __('Manual') }}</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">{{ __('Auto') }}</span>
                                    @endif
                                </td>
                                <td class="max-w-xs">
                                    @if ($log->monthly_attendance_id)
                                        <a href="{{ route('attendance.monthly-attendances.show', $log->monthly_attendance_id) }}"
                                            class="badge badge-info badge-sm mt-1 hover:badge-accent" title="{{ __('View Monthly Attendance') }}">
                                            {{ __('Monthly Attendance') }}
                                        </a>
                                    @endif
                                </td>
                                <td class="flex gap-2">
                                    <a href="{{ route('attendance.attendance-logs.show', $log) }}" class="btn btn-sm btn-ghost">
                                        {{ __('Show') }}
                                    </a>
                                    @can('attendance.attendance-logs.edit')
                                        <a href="{{ route('attendance.attendance-logs.edit', $log) }}" class="btn btn-sm btn-info">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                    @can('attendance.attendance-logs.edit')
                                        <form action="{{ route('attendance.attendance-logs.recalculate', $log) }}" method="POST" class="inline-block"
                                            onsubmit="return confirm('{{ __('Recalculate this log?') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                {{ __('Recalculate') }}
                                            </button>
                                        </form>
                                    @endcan
                                    @can('attendance.attendance-logs.delete')
                                        <form action="{{ route('attendance.attendance-logs.destroy', $log) }}" method="POST" class="inline-block"
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
                                    {{ __('No attendance logs found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $attendanceLogs->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
