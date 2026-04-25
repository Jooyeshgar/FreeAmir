<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Portal') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

        <div class="stat bg-base-100 shadow rounded-box">
            <div class="stat-figure text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <div class="stat-title">{{ __('Full Name') }}</div>
            <div class="stat-value text-lg">{{ $employee->first_name }} {{ $employee->last_name }}</div>
            <div class="stat-desc">{{ $employee->national_code }}</div>
        </div>

        <div class="stat bg-base-100 shadow rounded-box">
            <div class="stat-figure text-warning">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="stat-title">{{ __('Requests') }}</div>
            <div class="stat-value text-warning">{{ convertToFarsi($requests) }}</div>
            <div class="stat-desc">
                <a href="{{ route('employee-portal.personnel-requests.index') }}" class="link link-warning">
                    {{ __('View Requests') }}
                </a>
            </div>
        </div>

        @isset($lastMonthlyAttendance)
            <a href="{{ route('employee-portal.monthly-attendances.show', $lastMonthlyAttendance) }}"
                class="stat bg-base-100 shadow rounded-box hover:bg-base-200 transition">
                <div class="stat-figure text-info">...</div>
                <div class="stat-title">{{ __('Last Monthly Attendances') }}</div>
                <div class="stat-desc">{{ \App\Models\MonthlyAttendance::MONTH_NAMES[$lastMonthlyAttendance->month] ?? $lastMonthlyAttendance->month }}</div>
            </a>
        @else
            <a href="#" class="stat bg-base-100 shadow rounded-box opacity-50 cursor-not-allowed">
                <div class="stat-figure text-info">...</div>
                <div class="stat-title">{{ __('Last Monthly Attendances') }}</div>
                <div class="stat-desc">{{ __('No monthly attendance records found.') }}</div>
            </a>
        @endisset

        <a href="{{ route('employee-portal.payrolls') }}" class="stat bg-base-100 shadow rounded-box hover:bg-base-200 transition">
            <div class="stat-figure text-success">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div class="stat-title">{{ __('Payrolls') }}</div>
            <div class="stat-value text-success">—</div>
            <div class="stat-desc">{{ __('View payslips') }}</div>
        </a>

    </div>

    {{-- Recent attendance logs --}}
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between mb-2">
                <h2 class="card-title text-base">{{ __('Recent Attendance') }}</h2>
                <a href="{{ route('employee-portal.attendance-logs') }}" class="btn btn-sm btn-ghost">
                    {{ __('View All') }}
                </a>
            </div>

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
                        @forelse ($recentLogs as $log)
                            <tr>
                                <td>{{formatDate($log->log_date) }}</td>
                                <td>{{ $log->entry_time ? convertToFarsi($log->entry_time) : '—' }}</td>
                                <td>{{ $log->exit_time ? convertToFarsi($log->exit_time) : '—' }}</td>
                                <td>
                                    @if ($log->is_manual)
                                        <span class="badge badge-warning badge-sm">{{ __('Manual') }}</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">{{ __('Auto') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-gray-500 py-4">
                                    {{ __('No attendance logs found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
