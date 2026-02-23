<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Monthly Attendances') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            {{-- Filter bar --}}
            <form action="{{ route('monthly-attendances.index') }}" method="GET" class="flex flex-wrap items-end gap-3 mb-2">

                <div class="w-52">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Employee') }}</span>
                        </div>
                        <select name="employee_id" class="select select-bordered select-sm">
                            <option value="">{{ __('All Employees') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->first_name }} {{ $employee->last_name }}
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
                        <select name="month" class="select select-bordered select-sm">
                            <option value="">{{ __('All Months') }}</option>
                            @foreach (\App\Models\MonthlyAttendance::MONTH_NAMES as $num => $name)
                                <option value="{{ $num }}" {{ request('month') == $num ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <button type="submit" class="btn btn-sm btn-neutral self-end">
                    {{ __('Search') }}
                </button>
                <a href="{{ route('monthly-attendances.index') }}" class="btn btn-sm btn-ghost self-end">
                    {{ __('Reset') }}
                </a>
            </form>

            <div class="flex justify-end mb-2">
                @can('attendance.monthly-attendances.create')
                    <a href="{{ route('monthly-attendances.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Monthly Attendance') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </a>
                @endcan
            </div>

            <div class="overflow-x-auto">
                <table class="table w-full mt-2">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Year') }}</th>
                            <th>{{ __('Month') }}</th>
                            <th>{{ __('Work Days') }}</th>
                            <th>{{ __('Present') }}</th>
                            <th>{{ __('Absent') }}</th>
                            <th>{{ __('Overtime (min)') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monthlyAttendances as $attendance)
                            <tr>
                                <td>{{ $attendance->employee?->first_name }} {{ $attendance->employee?->last_name }}</td>
                                <td>{{ $attendance->year }}</td>
                                <td>{{ $attendance->month_name }}</td>
                                <td>{{ $attendance->work_days }}</td>
                                <td>{{ $attendance->present_days }}</td>
                                <td>{{ $attendance->absent_days }}</td>
                                <td>{{ $attendance->overtime }}</td>
                                <td class="flex gap-2">
                                    @can('attendance.monthly-attendances.show')
                                        <a href="{{ route('monthly-attendances.show', $attendance) }}" class="btn btn-sm btn-info">
                                            {{ __('View') }}
                                        </a>
                                    @endcan
                                    @can('attendance.monthly-attendances.edit')
                                        <a href="{{ route('monthly-attendances.edit', $attendance) }}" class="btn btn-sm btn-warning">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                    @can('attendance.monthly-attendances.delete')
                                        <form action="{{ route('monthly-attendances.destroy', $attendance) }}" method="POST" class="inline-block"
                                            onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">
                                    {{ __('No monthly attendance records found.') }}
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
