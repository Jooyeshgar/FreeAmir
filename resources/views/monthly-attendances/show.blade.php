<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Monthly Attendance Detail') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    {{-- Summary card --}}
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="card-title text-lg">
                        {{ $monthlyAttendance->employee?->first_name }}
                        {{ $monthlyAttendance->employee?->last_name }}
                        &mdash;
                        {{ $monthlyAttendance->month_name }} {{ convertToFarsi($monthlyAttendance->year) }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        @php
                            $shift = $monthlyAttendance->employee?->workShift;
                            $shiftStart = $shift ? convertToFarsi(substr($shift->start_time, 0, 5)) : \App\Services\AttendanceService::DEFAULT_SHIFT_START;
                            $shiftEnd = $shift ? convertToFarsi(substr($shift->end_time, 0, 5)) : \App\Services\AttendanceService::DEFAULT_SHIFT_END;
                            $shiftName = $shift?->name;
                        @endphp
                        {{ __('Shift') }}:
                        @if ($shiftName)
                            <span class="font-medium">{{ $shiftName }}</span>
                            ({{ $shiftStart }} &ndash; {{ $shiftEnd }})
                        @else
                            {{ $shiftStart }} &ndash; {{ $shiftEnd }}
                        @endif
                    </p>
                </div>

                <div class="flex gap-2 flex-wrap">
                    @if ($isAdminView ?? false)
                        @can('attendance.monthly-attendances.edit')
                            <a href="{{ route('attendance.monthly-attendances.edit', $monthlyAttendance) }}" class="btn btn-sm btn-warning">
                                {{ __('Edit') }}
                            </a>
                        @endcan
                        @can('attendance.monthly-attendances.delete')
                            <form action="{{ route('attendance.monthly-attendances.destroy', $monthlyAttendance) }}" method="POST" class="inline-block"
                                onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-error">
                                    {{ __('Delete') }}
                                </button>
                            </form>
                        @endcan
                    @endif
                    <a href="{{ $backRoute ?? route('attendance.monthly-attendances.index') }}" class="btn btn-sm btn-ghost">
                        {{ __('Back') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            {{-- Stats grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mt-4">
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Work Days') }}</div>
                    <div class="stat-value text-base">{{ convertToFarsi($monthlyAttendance->work_days) }}</div>
                </div>
                <div class="stat bg-success/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Present') }}</div>
                    <div class="stat-value text-base text-success">{{ convertToFarsi($monthlyAttendance->present_days) }}</div>
                </div>
                <div class="stat bg-error/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Absent') }}</div>
                    <div class="stat-value text-base text-error">{{ convertToFarsi($monthlyAttendance->absent_days) }}</div>
                </div>
                <div class="stat bg-warning/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Overtime') }}</div>
                    <div class="stat-value text-base text-warning">{{ formatMinutesAsTime($monthlyAttendance->overtime + $monthlyAttendance->auto_overtime) }}</div>
                </div>
                <div class="stat bg-error/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Undertime') }}</div>
                    <div class="stat-value text-base text-error">{{ formatMinutesAsTime($monthlyAttendance->undertime) }}</div>
                </div>
                <div class="stat bg-info/20 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Mission') }}</div>
                    <div class="stat-value text-base text-info">{{ formatMinutesAsTime($monthlyAttendance->mission) }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Paid Leave') }}</div>
                    <div class="stat-value text-base">{{ formatMinutesAsTime($monthlyAttendance->paid_leave) }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Unpaid Leave') }}</div>
                    <div class="stat-value text-base">{{ formatMinutesAsTime($monthlyAttendance->unpaid_leave) }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Friday Work') }}</div>
                    <div class="stat-value text-base">{{ formatMinutesAsTime($monthlyAttendance->friday) }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Holiday Work') }}</div>
                    <div class="stat-value text-base">{{ formatMinutesAsTime($monthlyAttendance->holiday) }}</div>
                </div>
            </div>

            {{-- Recalculate sub-form --}}
            @if ($isAdminView ?? false)
                @can('attendance.monthly-attendances.edit')
                    <div class="divider">{{ __('Recalculate from Logs') }}</div>
                    <form action="{{ route('attendance.monthly-attendances.recalculate', $monthlyAttendance) }}" method="POST" class="flex flex-wrap items-end gap-4">
                        @csrf
                        <div class="w-44">
                            <x-date-picker name="start_date" id="start_date" title="{{ __('Start Date') }}" :value="old('start_date') ?? formatDate($monthlyAttendance->start_date, 'Y/m/d')" required />
                        </div>
                        <div class="w-44">
                            <x-input name="duration" id="duration" type="number" title="{{ __('Duration (days)') }}" :value="old('duration') ?? $monthlyAttendance->duration" required />
                        </div>
                        <button type="submit" class="btn btn-sm btn-accent self-end">
                            {{ __('Recalculate') }}
                        </button>
                    </form>
                @endcan
            @endif

            {{-- Payroll section --}}
            @if ($isAdminView ?? false)
                <div class="divider">{{ __('Payroll') }}</div>
                @if ($monthlyAttendance->payroll)
                    <div class="overflow-x-auto">
                        <table class="table table-sm w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Issue Date') }}</th>
                                    <th>{{ __('Decree') }}</th>
                                    <th class="text-end">{{ __('Total Earnings') }}</th>
                                    <th class="text-end">{{ __('Total Deductions') }}</th>
                                    <th class="text-end">{{ __('Employer Insurance') }}</th>
                                    <th class="text-end">{{ __('Net Payment') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $payroll = $monthlyAttendance->payroll; @endphp
                                <tr>
                                    <td>{{ formatDate($payroll->issue_date) }}</td>
                                    <td><a href="{{ route('salary.salary-decrees.edit', $payroll->decree->id) }}">{{ $payroll->decree->name }}</a></td>
                                    <td class="text-end text-success">{{ formatNumber($payroll->total_earnings) }}</td>
                                    <td class="text-end text-error">{{ formatNumber($payroll->total_deductions) }}</td>
                                    <td class="text-end">{{ formatNumber($payroll->employer_insurance) }}</td>
                                    <td class="text-end font-semibold text-primary">{{ formatNumber($payroll->net_payment) }}</td>
                                    <td>
                                        @if ($payroll->status === 'draft')
                                            <span class="badge badge-warning badge-sm">{{ __('Draft') }}</span>
                                        @elseif ($payroll->status === 'approved')
                                            <span class="badge badge-success badge-sm">{{ __('Approved') }}</span>
                                        @else
                                            <span class="badge badge-info badge-sm">{{ __('Paid') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('salary.payrolls.show', $payroll) }}" class="btn btn-xs btn-primary">
                                            {{ __('View Payroll') }}
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    @can('salary.payrolls.create')
                        @if ($decrees->isEmpty())
                            <p class="text-sm text-warning">{{ __('No active salary decrees found for this employee. Please create one first.') }}</p>
                            <a href="{{ route('salary.salary-decrees.create', ['employee' => $monthlyAttendance->employee]) }}" class="btn btn-sm btn-warning">
                                {{ __('Create Decree') }}
                            </a>
                        @else
                            <form action="{{ route('attendance.monthly-attendances.payroll.store', $monthlyAttendance) }}" method="POST"
                                class="flex flex-wrap items-end gap-4">
                                @csrf
                                @php
                                    $decreeOptions = $decrees->mapWithKeys(
                                        fn($d) => [$d->id => ($d->name ?? __('Decree') . ' #' . $d->id) . ' (' . formatDate($d->start_date) . ')'],
                                    );
                                @endphp
                                <div class="w-64">
                                    <x-select name="decree_id" id="decree_id" title="" :options="$decreeOptions" required />
                                </div>
                                <button type="submit" class="btn btn-sm btn-success self-end">
                                    {{ __('Create Payroll') }}
                                </button>
                            </form>
                        @endif
                    @endcan
                @endif
            @endif {{-- isAdminView --}}

            <div class="divider">{{ __('Daily Attendance Logs') }}</div>

            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead>
                        <tr>
                            <th colspan="2">{{ __('Date') }}</th>
                            <th>{{ __('Entry') }}</th>
                            <th>{{ __('Exit') }}</th>
                            <th>{{ __('Worked') }}</th>
                            <th>{{ __('Leave') }}</th>
                            <th>{{ __('Overtime') }}</th>
                            <th>{{ __('Delay') }}</th>
                            <th>{{ __('Early Leave') }}</th>
                            <th>{{ __('Status') }}</th>
                            @if ($isAdminView ?? false)
                                @can('attendance.attendance-logs.edit')
                                    <th>
                                        <form action="{{ route('attendance.attendance-logs.recalculate-all', $monthlyAttendance) }}" method="POST" class="inline-block mb-0"
                                            onsubmit="return confirm('{{ __('Recalculate all attendance logs? Stored values will be overwritten with the computed values.') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-primary">{{ __('Recalculate All') }}</button>
                                        </form>
                                    </th>
                                @endcan
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($allDays as $log)
                            @php
                                $isPlaceholder = isset($log->_placeholder) && $log->_placeholder;
                                $placeholderIsFriday = $isPlaceholder && !empty($log->_is_friday);
                                $placeholderIsHoliday = $isPlaceholder && !empty($log->_is_holiday);
                                $isOffDay = $isPlaceholder ? $placeholderIsFriday || $placeholderIsHoliday : $log->is_friday || $log->is_holiday;
                            @endphp
                            <tr class="{{ $isOffDay ? 'bg-base-200' : '' }} {{ $isPlaceholder && !$isOffDay ? 'opacity-50' : '' }}">
                                <td>{{ formatDate($log->log_date, 'l') }}</td>
                                @if ($isPlaceholder)
                                    <td>
                                        {{ formatDate($log->log_date) }}
                                    </td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>
                                        @if ($placeholderIsHoliday)
                                            <span class="badge badge-warning badge-sm">{{ __('Holiday') }}</span>
                                        @elseif ($placeholderIsFriday)
                                            <span class="badge badge-ghost badge-sm">{{ __('Friday') }}</span>
                                        @else
                                            <span class="badge badge-error badge-sm">{{ __('Absent') }}</span>
                                        @endif
                                    </td>
                                    @if ($isAdminView ?? false)
                                        @can('attendance.attendance-logs.edit')
                                            <td></td>
                                        @endcan
                                    @endif
                                @else
                                    <td>
                                        <a href="{{ route('attendance.attendance-logs.show', $log) }}">{{ formatDate($log->log_date) }}</a>
                                    </td>
                                    <td>{{ $log->entry_time ? convertToFarsi($log->entry_time) : '—' }}</td>
                                    <td>{{ $log->exit_time ? convertToFarsi($log->exit_time) : '—' }}</td>
                                    <td>{{ formatMinutesAsTime($log->worked) }}</td>
                                    <td>{{ formatMinutesAsTime($log->paid_leave) }}</td>
                                    <td>{{ formatMinutesAsTime($log->overtime + $log->auto_overtime) }}</td>
                                    <td>{{ formatMinutesAsTime($log->delay) }}</td>
                                    <td>{{ formatMinutesAsTime($log->early_leave) }}</td>
                                    <td>
                                        @if ($log->is_holiday)
                                            <span class="badge badge-warning badge-sm">{{ __('Holiday') }}</span>
                                        @elseif ($log->is_friday)
                                            <span class="badge badge-ghost badge-sm">{{ __('Friday') }}</span>
                                        @elseif ($log->paid_leave > 0)
                                            <span class="badge badge-info badge-sm">{{ __('Paid Leave') }}</span>
                                        @elseif ($log->unpaid_leave > 0)
                                            <span class="badge badge-error badge-sm">{{ __('Unpaid Leave') }}</span>
                                        @elseif ($log->remote_work > 0)
                                            <span class="badge badge-primary badge-sm">{{ __('Remote Work') }}</span>
                                        @elseif ($log->mission > 0)
                                            <span class="badge badge-accent badge-sm">{{ __('Mission') }}</span>
                                        @elseif ($log->worked > 0)
                                            <span class="badge badge-success badge-sm">{{ __('Present') }}</span>
                                        @else
                                            <span class="badge badge-error badge-sm">{{ __('Absent') }}</span>
                                        @endif
                                        @if ($log->is_manual)
                                            <span class="badge badge-ghost badge-sm">{{ __('Manual') }}</span>
                                        @endif
                                    </td>
                                    @if ($isAdminView ?? false)
                                        @can('attendance.attendance-logs.edit')
                                            <td>
                                                <a href="{{ route('attendance.attendance-logs.edit', $log) }}" class="btn btn-xs btn-ghost">
                                                    {{ __('Edit') }}
                                                </a>
                                                <form action="{{ route('attendance.attendance-logs.recalculate', $log) }}" method="POST" class="inline-block mb-0"
                                                    onsubmit="return confirm('{{ __('Recalculate this attendance log? Stored values will be overwritten with the computed values.') }}')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-ghost">
                                                        {{ __('Recalculate') }}
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    @endif
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-gray-500">
                                    {{ __('No attendance logs linked to this record.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
