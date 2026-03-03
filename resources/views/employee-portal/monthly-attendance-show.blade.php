<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Monthly Attendance Detail') }} — {{ $monthlyAttendance->month_name }} {{ $monthlyAttendance->year }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    {{-- Summary stats --}}
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <div class="flex items-center justify-between gap-4 flex-wrap mb-4">
                <h2 class="card-title">
                    {{ $employee->first_name }} {{ $employee->last_name }}
                    &mdash;
                    {{ $monthlyAttendance->month_name }} {{ $monthlyAttendance->year }}
                </h2>
                <a href="{{ route('employee-portal.monthly-attendances') }}" class="btn btn-sm btn-ghost">
                    {{ __('Back') }}
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Work Days') }}</div>
                    <div class="stat-value text-base">{{ $monthlyAttendance->work_days }}</div>
                </div>
                <div class="stat bg-success/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Present') }}</div>
                    <div class="stat-value text-base text-success">{{ $monthlyAttendance->present_days }}</div>
                </div>
                <div class="stat bg-error/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Absent') }}</div>
                    <div class="stat-value text-base text-error">{{ $monthlyAttendance->absent_days }}</div>
                </div>
                <div class="stat bg-warning/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Overtime (min)') }}</div>
                    <div class="stat-value text-base text-warning">{{ $monthlyAttendance->overtime }}</div>
                </div>
                <div class="stat bg-info/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Mission Days') }}</div>
                    <div class="stat-value text-base text-info">{{ $monthlyAttendance->mission_days }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Paid Leave') }}</div>
                    <div class="stat-value text-base">{{ $monthlyAttendance->paid_leave_days }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Unpaid Leave') }}</div>
                    <div class="stat-value text-base">{{ $monthlyAttendance->unpaid_leave_days }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Fridays') }}</div>
                    <div class="stat-value text-base">{{ $monthlyAttendance->friday }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Holidays') }}</div>
                    <div class="stat-value text-base">{{ $monthlyAttendance->holiday }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daily log breakdown --}}
    @if ($monthlyAttendance->logs->isNotEmpty())
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-base mb-2">{{ __('Daily Attendance Logs') }}</h2>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Entry Time') }}</th>
                                <th>{{ __('Exit Time') }}</th>
                                <th>{{ __('Type') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($monthlyAttendance->logs as $log)
                                <tr>
                                    <td>{{ $log->log_date->format('Y-m-d') }}</td>
                                    <td>{{ $log->entry_time ?? '—' }}</td>
                                    <td>{{ $log->exit_time ?? '—' }}</td>
                                    <td>
                                        @if ($log->is_manual)
                                            <span class="badge badge-warning badge-sm">{{ __('Manual') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('Auto') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
