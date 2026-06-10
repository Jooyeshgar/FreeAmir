@if (! ($hasPersonalData ?? false))
    <div class="alert alert-warning">
        <span>{{ __('Your user is not linked to an employee record.') }}</span>
    </div>
@else
    @php
        $monthNames = \App\Models\MonthlyAttendance::MONTH_NAMES;
        $approvedCount = $requestsCount['approved'] ?? 0;
        $rejectedCount = $requestsCount['rejected'] ?? 0;
        $pendingCount = $requestsCount['pending'] ?? 0;

        $personalCards = [
            [
                'tone' => 'primary',
                'title' => __('Full Name'),
                'value' => $employee->first_name . ' ' . $employee->last_name,
                'meta' => localizeNumber($employee->national_code ?? '-'),
                'href' => route('employee-portal.employee.show'),
                'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            ],
            [
                'tone' => 'warning',
                'title' => __('Requests'),
                'value' => localizeNumber($approvedCount + $rejectedCount + $pendingCount),
                'meta' => __('Approved') . ': ' . localizeNumber($approvedCount) . ' • ' . __('In Pending') . ': ' . localizeNumber($pendingCount) . ' • ' . __('rejected') . ': ' . localizeNumber($rejectedCount),
                'href' => route('employee-portal.personnel-requests.index'),
                'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
            [
                'tone' => 'secondary',
                'title' => __('Last Monthly Attendances'),
                'value' => isset($lastMonthlyAttendance) ? ($monthNames[$lastMonthlyAttendance->month] ?? $lastMonthlyAttendance->month) : __('No monthly attendance records found.'),
                'meta' => isset($lastMonthlyAttendance) ? localizeNumber($lastMonthlyAttendance->year) : '',
                'href' => isset($lastMonthlyAttendance) ? route('employee-portal.monthly-attendances.show', $lastMonthlyAttendance) : null,
                'icon' => 'M8 3v4m8-4v4M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2zm-2 5h18',
            ],
            [
                'tone' => 'info',
                'title' => __('Last payslips'),
                'value' => isset($lastPayroll) ? ($monthNames[$lastPayroll->month] ?? $lastPayroll->month) : __('No payslips records found.'),
                'meta' => isset($lastPayroll) ? localizeNumber($lastPayroll->year) : '',
                'href' => isset($lastPayroll) ? route('employee-portal.payrolls.show', $lastPayroll) : null,
                'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
            ],
        ];
    @endphp

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($personalCards as $card)
            @php
                $tone = [
                    'info' => ['card' => 'border-sky-500/25 bg-sky-500/10', 'icon' => 'bg-sky-500/15 text-sky-500'],
                    'error' => ['card' => 'border-rose-500/25 bg-rose-500/10', 'icon' => 'bg-rose-500/15 text-rose-500'],
                    'success' => ['card' => 'border-emerald-500/25 bg-emerald-500/10', 'icon' => 'bg-emerald-500/15 text-emerald-500'],
                    'primary' => ['card' => 'border-primary/25 bg-primary/10', 'icon' => 'bg-primary/15 text-primary'],
                    'warning' => ['card' => 'border-amber-500/25 bg-amber-500/10', 'icon' => 'bg-amber-500/15 text-amber-500'],
                    'secondary' => ['card' => 'border-violet-500/25 bg-violet-500/10', 'icon' => 'bg-violet-500/15 text-violet-500'],
                ][$card['tone']];
                $cardClasses = 'card border shadow-sm ' . $tone['card'] . ' ' . ($card['href'] ? 'transition hover:shadow-md' : 'opacity-70');
            @endphp

            @if ($card['href'])
                <a href="{{ $card['href'] }}" class="{{ $cardClasses }}">
            @else
                <div class="{{ $cardClasses }}">
            @endif
                <div class="card-body gap-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="rounded-lg p-2 {{ $tone['icon'] }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                            </svg>
                        </div>
                        <span class="text-xs text-base-content/60 text-right">{{ $card['title'] }}</span>
                    </div>

                    <div>
                        <div class="text-lg font-bold leading-7 text-base-content">{{ $card['value'] }}</div>
                        @if (! empty($card['meta']))
                            <div class="text-xs text-base-content/60">{{ $card['meta'] }}</div>
                        @endif
                    </div>
                </div>
            @if ($card['href'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </section>

    <article class="card border border-base-300 bg-base-100/90 shadow-sm">
        <div class="card-body p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="card-title text-base">{{ __('Recent Attendance') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Your last 5 attendance records') }}</p>
                </div>
                <a href="{{ route('employee-portal.attendance-logs') }}" class="btn btn-xs btn-ghost">
                    {{ __('View All') }}
                </a>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="table table-zebra table-sm">
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
                                <td>{{ formatDate($log->log_date) }}</td>
                                <td>{{ $log->entry_time ? localizeNumber($log->entry_time) : '—' }}</td>
                                <td>{{ $log->exit_time ? localizeNumber($log->exit_time) : '—' }}</td>
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
                                <td colspan="4" class="py-6 text-center text-sm text-base-content/55">
                                    {{ __('No attendance logs found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </article>
@endif
