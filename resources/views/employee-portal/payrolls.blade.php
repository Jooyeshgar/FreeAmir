<x-app-layout :title="__('My Payrolls')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Filter bar --}}
            <form action="{{ route('employee-portal.payrolls') }}" method="GET" class="flex flex-wrap items-end gap-3">
                <div class="w-36">
                    <select name="month" class="select select-sm" onchange="this.form.submit()">
                        <option value="">{{ __('All Months') }}</option>
                        @foreach (\App\Models\MonthlyAttendance::MONTH_NAMES as $num => $name)
                            <option value="{{ $num }}" {{ request('month') == $num ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="table w-full mt-2">
                    <thead>
                        <tr>
                            <th>{{ __('Month') }}</th>
                            <th>{{ __('Total Earnings') }}</th>
                            <th>{{ __('Total Deductions') }}</th>
                            <th>{{ __('Net Payment') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payrolls as $payroll)
                            <tr>
                                <td>{{ \App\Models\MonthlyAttendance::MONTH_NAMES[$payroll->month] ?? $payroll->month }}</td>
                                <td x-data="{ show: false }" @click="show = !show">
                                    <span x-show="!show">****</span>
                                    <span x-show="show" class="text-success">
                                        {{ formatNumber($payroll->total_earnings) }}
                                    </span>
                                </td>
                                <td x-data="{ show: false }" @click="show = !show">
                                    <span x-show="!show">****</span>
                                    <span x-show="show" class="text-error">
                                        {{ formatNumber($payroll->total_deductions) }}
                                    </span>
                                </td>
                                <td x-data="{ show: false }" @click="show = !show">
                                    <span x-show="!show">****</span>
                                    <span x-show="show" class="font-semibold">
                                        {{ formatNumber($payroll->net_payment) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $payroll->statusBadgeClass() }} badge-sm">{{ $payroll->statusLabel() }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('employee-portal.payrolls.show', $payroll) }}" class="btn btn-xs btn-outline">
                                        {{ __('View Detail') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-gray-500">
                                    {{ __('No payroll records found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $payrolls->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
