<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Attendance Logs') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Filter bar --}}
            <form action="{{ route('employee-portal.attendance-logs') }}" method="GET" class="flex flex-wrap items-end gap-3 mb-2">

                <div class="w-36">
                    <x-date-picker name="date_from" id="date_from" title="{{ __('From Date') }}" :value="request('date_from')" />
                </div>

                <div class="w-36">
                    <x-date-picker name="date_to" id="date_to" title="{{ __('To Date') }}" :value="request('date_to')" />
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
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendanceLogs as $log)
                            <tr>
                                <td>{{ $log->log_date->format('Y-m-d') }}</td>
                                <td>{{ $log->entry_time ?? '—' }}</td>
                                <td>{{ $log->exit_time ?? '—' }}</td>
                                <td>
                                    @if ($log->is_manual)
                                        <span class="badge badge-warning badge-sm">{{ __('Manual') }}</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">{{ __('Auto') }}</span>
                                    @endif
                                </td>
                                <td class="max-w-xs truncate" title="{{ $log->description }}">
                                    {{ $log->description ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-500">
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
