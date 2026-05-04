<x-app-layout :title="__('Attendance Log') . ' — ' . ($employee ? $employee->first_name . ' ' . $employee->last_name : '') . ' — ' . formatDate($attendanceLog->log_date)">
    <div class="card bg-base-100 shadow-xl">

        {{-- ══ Header ══════════════════════════════════════════════════════════ --}}
        <div
            class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:text-white dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ __('Attendance Log') }}
                    @if ($employee)
                        — {{ $employee->first_name }} {{ $employee->last_name }}
                    @endif
                </h2>
                <p class="mt-1 float-end text-gray-500 dark:text-gray-300">
                    {{ formatDate($attendanceLog->log_date) }}
                </p>
            </div>

            {{-- Badges --}}
            <div class="flex flex-wrap gap-2 mt-3">
                @if ($attendanceLog->is_manual)
                    <span class="badge badge-lg badge-warning gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 0l.172.172a2 2 0 010 2.828L12 16H9v-3z" />
                        </svg>
                        {{ __('Manual Entry') }}
                    </span>
                @else
                    <span class="badge badge-lg badge-ghost gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V9l-6-6z" />
                        </svg>
                        {{ __('Automatic Entry') }}
                    </span>
                @endif

                @if ($isFriday)
                    <span class="badge badge-lg badge-error gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {{ __('Friday (Off-day)') }}
                    </span>
                @endif

                @if ($isPublicHoliday)
                    <span class="badge badge-lg badge-error gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                        {{ __('Public Holiday') }}
                    </span>
                @endif

                @if ($isThursday && $thursdayStatus)
                    @php
                        $thursdayBadgeClass = match ($thursdayStatus) {
                            \App\Enums\ThursdayStatus::HOLIDAY => 'badge-error',
                            \App\Enums\ThursdayStatus::HALF_DAY => 'badge-warning',
                            default => 'badge-info',
                        };
                    @endphp
                    <span class="badge badge-lg {{ $thursdayBadgeClass }} gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ __('Thursday') }}: {{ $thursdayStatus->label() }}
                    </span>
                @endif

                @if ($attendanceLog->monthlyAttendance)
                    @php
                        $routeName = auth()->user()->can('attendance.monthly-attendances.show')
                            ? 'attendance.monthly-attendances.show'
                            : 'employee-portal.monthly-attendances.show';
                    @endphp

                    <a href="{{ route($routeName, $attendanceLog->monthly_attendance_id) }}" class="badge badge-lg badge-info gap-2 hover:badge-accent">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ __('Monthly Attendance') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="card-body space-y-8">
            <x-show-message-bags />

            {{-- ══ Section 1: Employee & Shift Info ═══════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">{{ __('Employee & Shift Information') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                    {{-- Employee --}}
                    <div class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60">
                        <div class="stat">
                            <div class="stat-title text-blue-500">{{ __('Employee') }}</div>
                            <div class="stat-value text-blue-600 text-xl">
                                {{ $employee ? $employee->first_name . ' ' . $employee->last_name : '—' }}
                            </div>
                            @if ($employee?->code)
                                <div class="stat-desc text-blue-400">{{ __('Code') }}: {{ $employee->code }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Shift Name --}}
                    <div class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60">
                        <div class="stat">
                            <div class="stat-title text-indigo-500">{{ __('Work Shift') }}</div>
                            <div class="stat-value text-indigo-600 text-xl">
                                {{ $workShift?->name ?? __('No Shift Assigned') }}
                            </div>
                            <div class="stat-desc text-indigo-400">
                                @if ($workShift)
                                    {{ __('Shift duration: :min min', ['min' => $shiftMinutes]) }}
                                @else
                                    {{ __('Default: :min min', ['min' => \App\Services\AttendanceService::DEFAULT_WORK_MINUTES_PER_DAY]) }}
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Log Date --}}
                    <div class="stats shadow bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200/60">
                        <div class="stat">
                            <div class="stat-title text-purple-500">{{ __('Log Date') }}</div>
                            <div class="stat-value text-purple-600 text-xl">{{ formatDate($attendanceLog->log_date, 'Y/m/d l') }}</div>
                            <div class="stat-desc text-purple-400">
                                @if ($isFriday)
                                    {{ __('Friday — Off Day') }}
                                @elseif ($isThursdayHoliday)
                                    {{ __('Thursday') }}: {{ $thursdayStatus->label() }} — {{ __('Off Day') }}
                                @elseif ($isPublicHoliday)
                                    {{ __('Public Holiday — Off Day') }}
                                @elseif ($isThursday && $thursdayStatus)
                                    {{ __('Thursday') }}: {{ $thursdayStatus->label() }}
                                    @if ($thursdayStatus === \App\Enums\ThursdayStatus::HALF_DAY && $workShift?->thursday_exit_time)
                                        — {{ __('Exit') }}: {{ substr($workShift->thursday_exit_time, 0, 5) }}
                                    @endif
                                @else
                                    {{ __('Working Day') }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Shift Details --}}
                @if ($workShift)
                    <div class="mt-4 overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3">{{ __('Shift Start') }}</th>
                                    <th class="px-4 py-3">{{ __('Shift End') }}</th>
                                    <th class="px-4 py-3">{{ __('Real Cutoff (Start + Float Before)') }}</th>
                                    <th class="px-4 py-3">{{ __('Float') }}</th>
                                    <th class="px-4 py-3">{{ __('Break') }}</th>
                                    <th class="px-4 py-3">{{ __('Thursday') }}</th>
                                    <th class="px-4 py-3">{{ __('Thursday Exit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="hover:bg-base-300">
                                    <td class="px-4 py-3 font-mono">{{ substr($workShift->start_time, 0, 5) }}</td>
                                    <td
                                        class="px-4 py-3 font-mono
                                        {{ $isThursday && $thursdayStatus === \App\Enums\ThursdayStatus::HALF_DAY ? 'line-through text-gray-400' : '' }}">
                                        {{ substr($workShift->end_time, 0, 5) }}
                                    </td>
                                    <td class="px-4 py-3 font-mono font-semibold text-warning">
                                        {{ $effectiveShiftStart ?? '—' }}
                                        @if ($workShift->float)
                                            <span class="badge badge-warning badge-xs ms-1">+{{ $workShift->float }} {{ __('min') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $workShift->float ?? 0 }} {{ __('min') }}</td>
                                    <td class="px-4 py-3">{{ $workShift->break ?? 0 }} {{ __('min') }}</td>
                                    <td class="px-4 py-3">{{ $workShift->thursday_status?->label() ?? '—' }}</td>
                                    <td
                                        class="px-4 py-3 font-mono
                                        {{ $isThursday && $thursdayStatus === \App\Enums\ThursdayStatus::HALF_DAY ? 'font-semibold text-warning' : '' }}">
                                        @if ($workShift->thursday_exit_time)
                                            {{ substr($workShift->thursday_exit_time, 0, 5) }}
                                            @if ($isThursday && $thursdayStatus === \App\Enums\ThursdayStatus::HALF_DAY)
                                                <span class="badge badge-warning badge-xs ms-1">{{ __('Active') }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ══ Section 2: Clock-in / Clock-out ═══════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">{{ __('Clock In / Out') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60">
                        <div class="stat">
                            <div class="stat-title text-emerald-500">{{ __('Entry Time') }}</div>
                            <div class="stat-value text-emerald-600 text-2xl font-mono">
                                {{ $attendanceLog->entry_time ? substr($attendanceLog->entry_time, 0, 5) : '—' }}
                            </div>
                            @if ($workShift && $attendanceLog->entry_time && $diffEntry !== null)
                                @php
                                    $grossDelay = $diffEntry; // signed: positive = late
                                    $netDelay = $computed['delay'];
                                    $leaveCover = max(0, (int) $attendanceLog->paid_leave + (int) $attendanceLog->mission);
                                @endphp
                                <div class="stat-desc {{ $netDelay > 0 ? 'text-error' : 'text-emerald-400' }}">
                                    @if ($grossDelay <= 0)
                                        {{ __(':min min early', ['min' => abs($grossDelay)]) }}
                                    @elseif ($netDelay === 0 && $grossDelay > 0)
                                        {{ __(':min min late (within grace)', ['min' => max(0, $grossDelay - $workShift->float)]) }}
                                    @else
                                        {{ __(':min min late', ['min' => $netDelay]) }}
                                        @if ($leaveCover > 0)
                                            <span class="opacity-60">({{ __('gross: :min min', ['min' => $grossDelay]) }})</span>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="stats shadow bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200/60">
                        <div class="stat">
                            <div class="stat-title text-orange-500">{{ __('Exit Time') }}</div>
                            <div class="stat-value text-orange-600 text-2xl font-mono">
                                {{ $attendanceLog->exit_time ? substr($attendanceLog->exit_time, 0, 5) : '—' }}
                            </div>
                            @if ($workShift && $attendanceLog->exit_time && $diffExit !== null)
                                <div class="stat-desc {{ ($computed['early_leave'] ?? 0) > 0 ? 'text-error' : 'text-orange-400' }}">
                                    @if ($diffExit >= 0)
                                        {{ __(':min min overtime after shift end', ['min' => $diffExit]) }}
                                    @else
                                        {{ __(':min min early leave', ['min' => $computed['early_leave'] ?? 0]) }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══ Section 3: Stored vs Computed Comparison ══════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">{{ __('Calculation Check') }}</div>
                <p class="text-sm text-gray-500 mb-4">
                    {{ __('Stored values are what is currently saved in the database. Computed values are what the system would calculate right now. If they differ, use the Recalculate button to update.') }}
                </p>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">{{ __('Field') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Stored') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Computed') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Match?') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $fields = [
                                    'worked' => __('Worked'),
                                    'delay' => __('Delay'),
                                    'early_leave' => __('Early Leave'),
                                    'overtime' => __('Overtime'),
                                    'auto_overtime' => __('Auto overtime'),
                                    'mission' => __('Mission'),
                                ];
                            @endphp
                            @foreach ($fields as $key => $label)
                                @php
                                    $stored = (int) ($attendanceLog->{$key} ?? 0);
                                    $calc = (int) ($computed[$key] ?? 0);
                                    $matches = $stored === $calc;
                                @endphp
                                <tr class="hover:bg-base-300 {{ !$matches ? 'bg-error/10' : '' }}">
                                    <td class="px-4 py-3 font-medium">{{ $label }}</td>
                                    <td class="px-4 py-3 text-center font-mono">{{ formatMinutesAsTime($stored) }}</td>
                                    <td class="px-4 py-3 text-center font-mono">{{ formatMinutesAsTime($calc) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($matches)
                                            <span class="badge badge-success badge-sm">✓</span>
                                        @else
                                            <span class="badge badge-error badge-sm">✗</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @php
                                // Check is_friday and is_holiday flags too
                                $storedFriday = (bool) $attendanceLog->is_friday;
                                $storedHoliday = (bool) $attendanceLog->is_holiday;
                                $fridayMatch = $storedFriday === $isFriday;
                                $holidayMatch = $storedHoliday === $isHoliday;
                            @endphp
                            <tr class="hover:bg-base-300 {{ !$fridayMatch ? 'bg-error/10' : '' }}">
                                <td class="px-4 py-3 font-medium">{{ __('Is Friday Flag') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-sm {{ $storedFriday ? 'badge-error' : 'badge-ghost' }}">
                                        {{ $storedFriday ? __('Yes') : __('No') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-sm {{ $isFriday ? 'badge-error' : 'badge-ghost' }}">
                                        {{ $isFriday ? __('Yes') : __('No') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($fridayMatch)
                                        <span class="badge badge-success badge-sm">✓</span>
                                    @else
                                        <span class="badge badge-error badge-sm">✗</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="hover:bg-base-300 {{ !$holidayMatch ? 'bg-error/10' : '' }}">
                                <td class="px-4 py-3 font-medium">{{ __('Is Holiday Flag') }} <span
                                        class="text-xs text-gray-400">({{ __('incl. Thursday holiday') }})</span></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-sm {{ $storedHoliday ? 'badge-error' : 'badge-ghost' }}">
                                        {{ $storedHoliday ? __('Yes') : __('No') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge badge-sm {{ $isHoliday ? 'badge-error' : 'badge-ghost' }}">
                                        {{ $isHoliday ? __('Yes') : __('No') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($holidayMatch)
                                        <span class="badge badge-success badge-sm">✓</span>
                                    @else
                                        <span class="badge badge-error badge-sm">✗</span>
                                    @endif
                                </td>
                            </tr>
                            @php
                                $fields = [
                                    'paid_leave' => __('Paid Leave'),
                                    'remote_work' => __('Remote Work'),
                                    'unpaid_leave' => __('Early Leave'),
                                ];
                            @endphp
                            @foreach ($fields as $key => $label)
                                @php
                                    $stored = (int) ($attendanceLog->{$key} ?? 0);
                                @endphp
                                <tr class="hover:bg-base-300 {{ !$matches ? 'bg-error/10' : '' }}">
                                    <td class="px-4 py-3 font-medium">{{ $label }}</td>
                                    <td class="px-4 py-3 text-center font-mono">{{ formatMinutesAsTime($stored) }}</td>
                                    <td class="px-4 py-3 text-center font-mono">-</td>
                                    <td class="px-4 py-3 text-center"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ══ Section 4: Leave / Mission Details ════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">{{ __('Leave & Mission Details') }}</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="stats bg-base-100 shadow border border-base-300">
                        <div class="stat">
                            <div class="stat-title">{{ __('Paid Leave') }}</div>
                            <div class="stat-value text-xl">{{ (int) $attendanceLog->paid_leave }}</div>
                            <div class="stat-desc">{{ __('minutes') }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow border border-base-300">
                        <div class="stat">
                            <div class="stat-title">{{ __('Unpaid Leave') }}</div>
                            <div class="stat-value text-xl">{{ (int) $attendanceLog->unpaid_leave }}</div>
                            <div class="stat-desc">{{ __('minutes') }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow border border-base-300">
                        <div class="stat">
                            <div class="stat-title">{{ __('Mission') }}</div>
                            <div class="stat-value text-xl">{{ (int) $attendanceLog->mission }}</div>
                            <div class="stat-desc">{{ __('minutes') }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow border border-base-300">
                        <div class="stat">
                            <div class="stat-title">{{ __('Remote Work') }}</div>
                            <div class="stat-value text-xl">{{ (int) $attendanceLog->remote_work }}</div>
                            <div class="stat-desc">{{ __('minutes') }}</div>
                        </div>
                    </div>
                    <div class="stats bg-base-100 shadow border border-base-300 col-span-2 md:col-span-4">
                        <div class="stat">
                            <div class="stat-title">{{ __('Description') }}</div>
                            <div class="stat-value text-base font-normal break-words">
                                {{ $attendanceLog->description ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══ Section 5: Personnel Requests ════════════════════════════════ --}}
            <div>
                <div class="divider text-lg font-semibold">{{ __('Personnel Requests') }}</div>
                <p class="text-sm text-gray-500 mb-4">
                    {{ __('Approved requests for this date are applied to the calculation: leave and mission minutes reduce delay and early-leave penalties.') }}
                </p>
                @if ($personnelRequests->isEmpty())
                    <div class="alert alert-ghost border border-base-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
                        </svg>
                        <span class="text-gray-500">{{ __('No personnel requests found.') }}</span>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3">{{ __('Type') }}</th>
                                    <th class="px-4 py-3">{{ __('Start') }}</th>
                                    <th class="px-4 py-3">{{ __('End') }}</th>
                                    <th class="px-4 py-3 text-center">{{ __('Duration (min)') }}</th>
                                    <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                                    <th class="px-4 py-3">{{ __('Reason') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($personnelRequests as $pr)
                                    @php
                                        $statusClass = match ($pr->status) {
                                            'approved' => 'badge-success',
                                            'rejected' => 'badge-error',
                                            default => 'badge-warning',
                                        };
                                        $typeClass = match (true) {
                                            in_array($pr->request_type, \App\Enums\PersonnelRequestType::leaveTypes()) => 'badge-info',
                                            in_array($pr->request_type, \App\Enums\PersonnelRequestType::missionTypes()) => 'badge-accent',
                                            default => 'badge-ghost',
                                        };
                                        $durationMin = (int) $pr->start_date->diffInMinutes($pr->end_date);
                                    @endphp
                                    <tr class="hover:bg-base-300 {{ $pr->status === 'approved' ? '' : 'opacity-60' }}">
                                        <td class="px-4 py-3">
                                            <span class="badge badge-sm {{ $typeClass }}">{{ $pr->request_type->label() }}</span>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-sm">{{ formatDate($pr->start_date, 'Y/m/d H:i') }}</td>
                                        <td class="px-4 py-3 font-mono text-sm">{{ formatDate($pr->end_date, 'Y/m/d H:i') }}</td>
                                        <td class="px-4 py-3 text-center font-mono">{{ $durationMin }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="badge badge-sm {{ $statusClass }}">
                                                {{ ucfirst($pr->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $pr->reason ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ══ Actions ═══════════════════════════════════════════════════════ --}}
            <div class="card-actions justify-between mt-4">
                <a href="{{ url()->previous() }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>

                <div class="flex flex-wrap gap-2">
                    @can('attendance.attendance-logs.edit')
                        <a href="{{ route('attendance.attendance-logs.edit', $attendanceLog) }}" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 0l.172.172a2 2 0 010 2.828L12 16H9v-3z" />
                            </svg>
                            {{ __('Edit') }}
                        </a>

                        <form action="{{ route('attendance.attendance-logs.recalculate', $attendanceLog) }}" method="POST" class="inline-block"
                            onsubmit="return confirm('{{ __('Recalculate this attendance log? Stored values will be overwritten with the computed values.') }}')">
                            @csrf
                            <button type="submit" class="btn btn-warning gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {{ __('Recalculate') }}
                            </button>
                        </form>
                    @endcan

                    @can('attendance.attendance-logs.delete')
                        <form action="{{ route('attendance.attendance-logs.destroy', $attendanceLog) }}" method="POST" class="inline-block"
                            onsubmit="return confirm('{{ __('Are you sure you want to delete this attendance log?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-error gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                {{ __('Delete') }}
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
