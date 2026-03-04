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
                        {{ $monthlyAttendance->month_name }} {{ $monthlyAttendance->year }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        @php
                            $shift = $monthlyAttendance->employee?->workShift;
                            $shiftStart = $shift ? substr($shift->start_time, 0, 5) : \App\Services\AttendanceService::DEFAULT_SHIFT_START;
                            $shiftEnd = $shift ? substr($shift->end_time, 0, 5) : \App\Services\AttendanceService::DEFAULT_SHIFT_END;
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
                    @can('attendance.monthly-attendances.edit')
                        <a href="{{ route('monthly-attendances.edit', $monthlyAttendance) }}" class="btn btn-sm btn-warning">
                            {{ __('Edit') }}
                        </a>
                    @endcan
                    @can('attendance.monthly-attendances.delete')
                        <form action="{{ route('monthly-attendances.destroy', $monthlyAttendance) }}" method="POST" class="inline-block"
                            onsubmit="return confirm('{{ __('Are you sure?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-error">
                                {{ __('Delete') }}
                            </button>
                        </form>
                    @endcan
                    <a href="{{ route('monthly-attendances.index') }}" class="btn btn-sm btn-ghost">
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
                    <div class="stat-title text-xs">{{ __('Friday Work (min)') }}</div>
                    <div class="stat-value text-base">{{ $monthlyAttendance->friday }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box p-3">
                    <div class="stat-title text-xs">{{ __('Holiday Work (min)') }}</div>
                    <div class="stat-value text-base">{{ $monthlyAttendance->holiday }}</div>
                </div>
            </div>

            {{-- Recalculate sub-form --}}
            @can('attendance.monthly-attendances.edit')
                <div class="divider">{{ __('Recalculate from Logs') }}</div>
                <form action="{{ route('monthly-attendances.recalculate', $monthlyAttendance) }}" method="POST" class="flex flex-wrap items-end gap-4">
                    @csrf
                    <div class="w-44">
                        <x-date-picker name="start_date" id="start_date" title="{{ __('Start Date') }}" :value="old('start_date') ?? formatDate($monthlyAttendance->start_date)" required />
                    </div>
                    <div class="w-44">
                        <x-input name="duration" id="duration" type="number" title="{{ __('Duration (days)') }}" :value="old('duration') ?? $monthlyAttendance->duration" required />
                    </div>
                    <button type="submit" class="btn btn-sm btn-accent self-end">
                        {{ __('Recalculate') }}
                    </button>
                </form>
            @endcan

            {{-- Payroll section --}}
            <div class="divider">{{ __('Payroll') }}</div>
            @if ($monthlyAttendance->payroll)
                <div class="flex items-center gap-4 flex-wrap">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600">{{ __('Payroll exists for this period.') }}</span>
                        @if ($monthlyAttendance->payroll->status === 'draft')
                            <span class="badge badge-warning badge-sm">{{ __('Draft') }}</span>
                        @elseif ($monthlyAttendance->payroll->status === 'approved')
                            <span class="badge badge-success badge-sm">{{ __('Approved') }}</span>
                        @else
                            <span class="badge badge-info badge-sm">{{ __('Paid') }}</span>
                        @endif
                    </div>
                    <a href="{{ route('payrolls.show', $monthlyAttendance->payroll) }}" class="btn btn-sm btn-primary">
                        {{ __('View Payroll') }}
                    </a>
                </div>
            @else
                @can('salary.payrolls.create')
                    @if ($decrees->isEmpty())
                        <p class="text-sm text-warning">{{ __('No active salary decrees found for this employee. Please create one first.') }}</p>
                    @else
                        <form action="{{ route('monthly-attendances.payroll.store', $monthlyAttendance) }}" method="POST" class="flex flex-wrap items-end gap-4">
                            @csrf
                            <div class="w-64">
                                <label class="form-control w-full">
                                    <div class="label">
                                        <span class="label-text">{{ __('Salary Decree') }}</span>
                                    </div>
                                    <select name="decree_id" class="select select-bordered select-sm" required>
                                        <option value="">{{ __('Select Decree') }}</option>
                                        @foreach ($decrees as $decree)
                                            <option value="{{ $decree->id }}">
                                                {{ $decree->name ?? __('Decree') . ' #' . $decree->id }}
                                                ({{ formatDate($decree->start_date) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-success self-end">
                                {{ __('Create Payroll') }}
                            </button>
                        </form>
                    @endif
                @endcan
            @endif

            <div class="divider">{{ __('Daily Attendance Logs') }}</div>

            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Entry') }}</th>
                            <th>{{ __('Exit') }}</th>
                            <th>{{ __('Worked (min)') }}</th>
                            <th>{{ __('Overtime (min)') }}</th>
                            <th>{{ __('Delay (min)') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monthlyAttendance->logs as $log)
                            <tr class="{{ $log->is_friday || $log->is_holiday ? 'bg-base-200' : '' }}">
                                <td>{{ formatDate($log->log_date) }}</td>
                                <td>{{ $log->entry_time ?? '—' }}</td>
                                <td>{{ $log->exit_time ?? '—' }}</td>
                                <td>{{ $log->worked }}</td>
                                <td>{{ $log->overtime }}</td>
                                <td>{{ $log->delay }}</td>
                                <td>
                                    @if ($log->is_holiday)
                                        <span class="badge badge-warning badge-sm">{{ __('Holiday') }}</span>
                                    @elseif ($log->is_friday)
                                        <span class="badge badge-ghost badge-sm">{{ __('Friday') }}</span>
                                    @elseif ($log->paid_leave > 0)
                                        <span class="badge badge-info badge-sm">{{ __('Paid Leave') }}</span>
                                    @elseif ($log->unpaid_leave > 0)
                                        <span class="badge badge-error badge-sm">{{ __('Unpaid Leave') }}</span>
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
                                <td class="text-xs text-gray-500">{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">
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
