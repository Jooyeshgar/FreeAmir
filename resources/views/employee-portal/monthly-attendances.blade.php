<x-app-layout :title="__('My Monthly Attendances')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Filter bar --}}
            <form action="{{ route('employee-portal.monthly-attendances') }}" method="GET" class="flex flex-wrap items-end gap-3 mb-2">
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
                    <a href="{{ route('employee-portal.monthly-attendances') }}" class="btn btn-sm btn-ghost">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="table w-full mt-2">
                    <thead>
                        <tr>
                            <th>{{ __('Month') }}</th>
                            <th>{{ __('Work Days') }}</th>
                            <th>{{ __('Present') }}</th>
                            <th>{{ __('Absent') }}</th>
                            <th>{{ __('Overtime (min)') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monthlyAttendances as $ma)
                            <tr>
                                <td>{{ \App\Models\MonthlyAttendance::MONTH_NAMES[$ma->month] ?? $ma->month }}</td>
                                <td>{{ localizeNumber($ma->work_days) }}</td>
                                <td>
                                    <span class="text-success font-medium">{{ localizeNumber($ma->present_days) }}</span>
                                </td>
                                <td>
                                    <span class="{{ $ma->absent_days > 0 ? 'text-error' : '' }}">{{ localizeNumber($ma->absent_days) }}</span>
                                </td>
                                <td>{{ localizeNumber($ma->overtime) }}</td>
                                <td>
                                    <a href="{{ route('employee-portal.monthly-attendances.show', $ma) }}" class="btn btn-sm btn-info">
                                        {{ __('Details') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-gray-500">
                                    {{ __('No records found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $monthlyAttendances->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
