<x-app-layout :title="__('My Attendance Logs')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Filter bar --}}
            <form action="{{ route('employee-portal.attendance-logs') }}" method="GET" class="flex flex-wrap items-end gap-3 mb-4">

                <div class="w-36">
                    <x-date-picker name="date_from" id="date_from" title="{{ __('From Date') }}" :value="request('date_from')" />
                </div>

                <div class="w-36">
                    <x-date-picker name="date_to" id="date_to" title="{{ __('To Date') }}" :value="request('date_to')" />
                </div>

                <div class="w-36">
                    <label class="fieldset w-full">
                        <div class="label">
                            <span>{{ __('Entry Type') }}</span>
                        </div>
                        <select name="is_manual" class="select  select-sm">
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
                    <a href="{{ route('employee-portal.attendance-logs') }}" class="btn btn-sm btn-ghost">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="table w-full mt-2">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Entry Time') }}</th>
                            <th>{{ __('Exit Time') }}</th>
                            <th>{{ __('Worked (min)') }}</th>
                            <th>{{ __('Overtime (min)') }}</th>
                            <th>{{ __('Delay (min)') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Monthly Attendance') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendanceLogs as $log)
                            <tr class="{{ $log->is_friday || $log->is_holiday ? 'bg-base-200 text-gray-400' : '' }}">
                                <td class="font-medium">
                                    {{ formatDate($log->log_date) }}
                                    @if ($log->is_friday)
                                        <span class="badge badge-ghost badge-xs ms-1">{{ __('Friday') }}</span>
                                    @elseif ($log->is_holiday)
                                        <span class="badge badge-accent badge-xs ms-1">{{ __('Holiday') }}</span>
                                    @endif
                                </td>
                                <td>{{ $log->entry_time ? convertToFarsi(substr($log->entry_time, 0, 5)) : '—' }}</td>
                                <td>{{ $log->exit_time ? convertToFarsi(substr($log->exit_time, 0, 5)) : '—' }}</td>
                                <td>
                                    @if ($log->worked)
                                        <span class="text-success font-medium">{{ convertToFarsi($log->worked) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($log->overtime)
                                        <span class="text-info font-medium">{{ convertToFarsi($log->overtime) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($log->delay)
                                        <span class="text-warning font-medium">{{ convertToFarsi($log->delay) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="space-x-1 space-y-1">
                                    @if ($log->is_manual)
                                        <span class="badge badge-warning badge-sm">{{ __('Manual') }}</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">{{ __('Auto') }}</span>
                                    @endif
                                    @if ($log->paid_leave)
                                        <span class="badge badge-success badge-sm">{{ __('Paid Leave') }}</span>
                                    @endif
                                    @if ($log->unpaid_leave)
                                        <span class="badge badge-error badge-sm">{{ __('Unpaid Leave') }}</span>
                                    @endif
                                    @if ($log->mission)
                                        <span class="badge badge-info badge-sm">{{ __('Mission') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($log->monthly_attendance_id)
                                        <a href="{{ route('employee-portal.monthly-attendances.show', $log->monthly_attendance_id) }}"
                                            class="badge badge-info badge-sm hover:badge-accent" title="{{ __('View Monthly Attendance') }}">
                                            {{ __('Monthly Attendance') }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="max-w-xs truncate" title="{{ $log->description }}">
                                    {{ $log->description ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
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
