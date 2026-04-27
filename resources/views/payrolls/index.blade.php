<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <h2 class="card-title text-2xl">{{ __('Payroll Index') }}</h2>
                <div class="text-sm text-base-content/70">
                    {{ __('Total records') }}: <span class="font-semibold">{{ $payrolls->total() }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 mt-2">
                <div class="stat bg-base-200 rounded-box">
                    <div class="stat-title">{{ __('Total Income') }}</div>
                    <div class="stat-value text-success text-xl">{{ formatNumber($payrolls->sum('total_earnings')) }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box">
                    <div class="stat-title">{{ __('Total Deductions') }}</div>
                    <div class="stat-value text-error text-xl">{{ formatNumber($payrolls->sum('total_deductions')) }}</div>
                </div>
                <div class="stat bg-base-200 rounded-box">
                    <div class="stat-title">{{ __('Net Payment') }}</div>
                    <div class="stat-value text-primary text-xl">{{ formatNumber($payrolls->sum('net_payment')) }}</div>
                </div>
            </div>

            {{-- Filter bar --}}
            <form action="{{ route('salary.payrolls.index') }}" method="GET"
                class="mt-4 rounded-box border border-base-300 bg-base-200/40 p-4 flex flex-wrap items-end gap-3 mb-4">

                <div class="w-48">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Employee') }}</span>
                        </div>
                        <select name="employee_id" class="select select-bordered">
                            <option value="">{{ __('All Employees') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->last_name }}
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
                    <a href="{{ route('salary.payrolls.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Issue Date') }}</th>
                            <th>{{ __('Attendance') }}</th>
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
                                <td>
                                    <div class="font-medium">{{ $payroll->employee?->first_name ?? '—' }} {{ $payroll->employee?->last_name }}</div>
                                    <div class="text-xs text-base-content/70">{{ $payroll->employee?->code ?? '' }}</div>
                                </td>
                                <td>{{ formatDate($payroll->issue_date?->format('Y/m/d')) }}</td>
                                <td>
                                    @if ($payroll->monthlyAttendance)
                                        <div class="text-sm">
                                            {{ __('Duration') }}:
                                            <span class="font-semibold">{{ formatNumber($payroll->monthlyAttendance->duration) }}</span>
                                            {{ __('day(s)') }}
                                        </div>
                                        <a href="{{ route('attendance.monthly-attendances.show', $payroll->monthlyAttendance) }}" class="link link-primary text-xs">
                                            {{ __('View Monthly Attendance') }}
                                        </a>
                                    @else
                                        <span class="text-base-content/60">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($payroll->decree)
                                        <a href="{{ route('salary.salary-decrees.edit', $payroll->decree) }}" class="link link-primary">
                                            {{ $payroll->decree->name }}
                                        </a>
                                    @else
                                        —
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
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('salary.payrolls.show', $payroll) }}" class="btn btn-xs btn-outline btn-primary"
                                            title="{{ __('View Payroll') }}">
                                            {{ __('View') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-6 text-gray-500">
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
