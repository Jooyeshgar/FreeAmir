<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Preview') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title mb-2">{{ __('Import Preview') }}</h2>

            {{-- Summary banner --}}
            <div class="alert alert-info mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>
                    {{ __('Total records to import: :total', ['total' => $preview['total']]) }}
                    &mdash;
                    {{ __('Showing first :count rows below.', ['count' => count($preview['rows'])]) }}
                    &mdash;
                    @if ($duplicateMode === 'replace')
                        <span class="badge badge-warning badge-sm">{{ __('Duplicates: Replace') }}</span>
                    @else
                        <span class="badge badge-ghost badge-sm">{{ __('Duplicates: Ignore') }}</span>
                    @endif
                </span>
            </div>

            {{-- Unknown device IDs warning --}}
            @if (!empty($preview['unknown_devices']))
                <div class="alert alert-warning mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>
                        {{ __('Unknown device IDs (not mapped to any employee): :ids', ['ids' => implode(', ', $preview['unknown_devices'])]) }}
                    </span>
                </div>
            @endif

            {{-- Preview table --}}
            @if (count($preview['rows']) > 0)
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full text-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Device ID') }}</th>
                                <th>{{ __('Employee') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Time') }}</th>
                                <th>{{ __('Type') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['rows'] as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <span class="font-mono">{{ $row['device_id'] }}</span>
                                    </td>
                                    <td>
                                        @if ($row['employee_name'])
                                            {{ $row['employee_name'] }}
                                        @else
                                            <span class="badge badge-error badge-sm">{{ __('Unknown') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ gregorian_to_jalali_date($row['log_date']) }}</td>
                                    <td>{{ $row['log_time'] }}</td>
                                    <td>
                                        <span class="badge {{ $row['log_type'] === __('Check-in') ? 'badge-success' : 'badge-warning' }} badge-sm">
                                            {{ $row['log_type'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($preview['total'] > count($preview['rows']))
                    <p class="text-sm text-base-content/60 mt-2">
                        {{ __('… and :remaining more records.', ['remaining' => $preview['total'] - count($preview['rows'])]) }}
                    </p>
                @endif
            @else
                <div class="alert alert-warning">
                    <span>{{ __('No records found for the selected date range.') }}</span>
                </div>
            @endif

            {{-- Confirm / Go-back actions --}}
            <div class="flex flex-wrap gap-3 justify-end mt-6">
                <a href="{{ route('attendance-logs.import') }}" class="btn btn-ghost">
                    {{ __('Back') }}
                </a>

                @if ($preview['total'] > 0)
                    <form action="{{ route('attendance-logs.import.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="import_type" value="{{ $type->value }}">
                        @if ($dateFrom)
                            <input type="hidden" name="date_from_gregorian" value="{{ $dateFrom }}">
                        @endif
                        @if ($dateTo)
                            <input type="hidden" name="date_to_gregorian" value="{{ $dateTo }}">
                        @endif
                        <input type="hidden" name="tmp_path" value="{{ $path }}">
                        <input type="hidden" name="duplicate_mode" value="{{ $duplicateMode }}">

                        <button type="submit" class="btn btn-primary">
                            {{ __('Confirm Import') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
