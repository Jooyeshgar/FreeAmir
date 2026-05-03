<x-app-layout :title="__('My Payrolls')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Filter bar --}}
            <form action="{{ route('employee-portal.payrolls') }}" method="GET" class="flex flex-wrap items-end gap-3 mb-2">

                <div class="w-28">
                    <label class="fieldset w-full">
                        <div class="label">
                            <span>{{ __('Year') }}</span>
                        </div>
                        <input type="number" name="year" value="{{ request('year') }}" placeholder="{{ __('Year') }}" class="input  input-sm" />
                    </label>
                </div>

                <div class="w-36">
                    <label class="fieldset w-full">
                        <div class="label">
                            <span>{{ __('Month') }}</span>
                        </div>
                        <select name="month" class="select  select-sm">
                            <option value="">{{ __('All Months') }}</option>
                            @foreach (\App\Models\MonthlyAttendance::MONTH_NAMES as $num => $name)
                                <option value="{{ $num }}" {{ request('month') == $num ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="flex gap-2 items-end pb-1">
                    <button type="submit" class="btn btn-sm btn-primary">
                        {{ __('Search') }}
                    </button>
                    <a href="{{ route('employee-portal.payrolls') }}" class="btn btn-sm btn-ghost">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="table w-full mt-2">
                    <thead>
                        <tr>
                            <th>{{ __('Year') }}</th>
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
                                <td>{{ convertToFarsi($payroll->year) }}</td>
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
                                    @if ($payroll->status === 'paid')
                                        <span class="badge badge-success badge-sm">{{ __('Paid') }}</span>
                                    @elseif ($payroll->status === 'draft')
                                        <span class="badge badge-ghost badge-sm">{{ __('Draft') }}</span>
                                    @else
                                        <span class="badge badge-warning badge-sm">{{ $payroll->status }}</span>
                                    @endif
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
