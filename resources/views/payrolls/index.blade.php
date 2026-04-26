<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">{{ __('Payroll Index') }}</h2>

            {{-- Filter bar --}}
            <form action="{{ route('hr.employees.index') }}" method="GET" class="flex flex-wrap items-end gap-3 mb-4">

                <div class="w-48">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Employee') }}</span>
                        </div>
                        <select name="employee_id" class="select select-bordered">
                            <option value="">{{ __('All Employees') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="w-28">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Year') }}</span>
                        </div>
                        <input type="number" name="year" value="{{ request('year') }}" placeholder="{{ __('Year') }}" class="input input-bordered input-sm" />
                    </label>
                </div>

                <div class="w-36">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Month') }}</span>
                        </div>
                        <select name="month" class="select select-bordered">
                            <option value="">{{ __('All Months') }}</option>
                            @foreach (\App\Models\MonthlyAttendance::MONTH_NAMES as $monthIndex => $monthName)
                                <option value="{{ $monthIndex }}" {{ request('month') == $monthIndex ? 'selected' : '' }}>
                                    {{ $monthName }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                    <a href="{{ route('hr.employees.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>{{ __('Issue Date') }}</th>
                            <th>{{ __('Salary Decree') }}</th>
                            <th>{{ __('Total Income') }}</th>
                            <th>{{ __('Total Deductions') }}</th>
                            <th>{{ __('Employer Insurance') }}</th>
                            <th>{{ __('Net Payment') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payrolls as $payroll)
                            <tr>
                                <td>{{ formatDate($payroll->issue_date?->format('Y/m/d')) }}</td>
                                <td>
                                    @if ($payroll->decree)
                                        <a href="{{ route('salary.salary-decrees.edit', $payroll->decree) }}" class="link link-primary">
                                            {{ $payroll->decree->name }}
                                        </a>
                                    @else
                                        —
                                        </a>
                                    @endif
                                </td>
                                <td>{{ formatNumber($payroll->total_earnings) }}</td>
                                <td>{{ formatNumber($payroll->total_deductions) }}</td>
                                <td>{{ formatNumber($payroll->employer_insurance) }}</td>
                                <td>{{ formatNumber($payroll->net_payment) }}</td>
                                <td>
                                    @if ($payroll->status === 'paid')
                                        <span class="badge badge-success badge-sm">{{ __('Paid') }}</span>
                                    @elseif ($payroll->status === 'draft')
                                        <span class="badge badge-ghost badge-sm">{{ __('Draft') }}</span>
                                    @else
                                        <span class="badge badge-warning badge-sm">{{ $payroll->status }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($payroll->monthly_attendance_id)
                                        <a href="{{ route('attendance.monthly-attendances.show', $payroll->monthly_attendance_id) }}" class="btn btn-xs btn-outline"
                                            title="{{ __('View Attendance') }}">
                                            {{ __('Attendance') }}
                                        </a>
                                    @endif
                                    <a href="{{ route('salary.payrolls.show', $payroll) }}" class="btn btn-xs btn-outline" title="{{ __('View Payroll') }}">
                                        {{ __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">
                                    {{ __('No payrolls found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $payrolls->links() }}
        </div>
    </div>
</x-app-layout>
