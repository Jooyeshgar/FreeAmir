<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Work Shifts') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between gap-3">
                <form action="{{ route('work-shifts.index') }}" method="GET" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Filter by name') }}"
                        class="px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                        {{ __('Search') }}
                    </button>
                </form>

                @can('attendance.work-shifts.create')
                    <a href="{{ route('work-shifts.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Work Shift') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </a>
                @endcan
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Start Time') }}</th>
                        <th>{{ __('End Time') }}</th>
                        <th>{{ __('Break (min)') }}</th>
                        <th>{{ __('Float Before') }}</th>
                        <th>{{ __('Float After') }}</th>
                        <th>{{ __('Crosses Midnight') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workShifts as $workShift)
                        <tr>
                            <td>{{ $workShift->name }}</td>
                            <td>{{ substr($workShift->start_time, 0, 5) }}</td>
                            <td>{{ substr($workShift->end_time, 0, 5) }}</td>
                            <td>{{ $workShift->break }}</td>
                            <td>{{ $workShift->float_before }}</td>
                            <td>{{ $workShift->float_after }}</td>
                            <td>
                                @if ($workShift->crosses_midnight)
                                    <span class="badge badge-warning">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($workShift->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-error">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                @can('attendance.work-shifts.edit')
                                    <a href="{{ route('work-shifts.edit', $workShift) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('attendance.work-shifts.delete')
                                    <form action="{{ route('work-shifts.destroy', $workShift) }}" method="POST" class="inline-block"
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
                            <td colspan="9" class="text-center py-6 text-gray-400">
                                {{ __('No work shifts found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $workShifts->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
