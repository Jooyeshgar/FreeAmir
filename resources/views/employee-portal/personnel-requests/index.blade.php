<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Requests') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Tabs --}}
            <div role="tablist" class="tabs tabs-lifted tabs-lg mb-4">

                <a role="tab" href="{{ route('employee-portal.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'leaves'])) }}"
                    class="tab {{ $tab === 'leaves' ? 'tab-active' : '' }}">
                    {{ __('Leaves') }}
                    @if ($pendingCounts['leaves'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['leaves'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('employee-portal.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'missions'])) }}"
                    class="tab {{ $tab === 'missions' ? 'tab-active' : '' }}">
                    {{ __('Missions') }}
                    @if ($pendingCounts['missions'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['missions'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('employee-portal.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'work_orders'])) }}"
                    class="tab {{ $tab === 'work_orders' ? 'tab-active' : '' }}">
                    {{ __('Work Orders') }}
                    @if ($pendingCounts['work_orders'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['work_orders'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('employee-portal.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'other'])) }}"
                    class="tab {{ $tab === 'other' ? 'tab-active' : '' }}">
                    {{ __('Other') }}
                    @if ($pendingCounts['other'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['other'] }}</span>
                    @endif
                </a>

            </div>

            {{-- New request button --}}
            <div class="flex justify-end mb-2">
                <a href="{{ route('employee-portal.personnel-requests.create', ['tab' => $tab]) }}" class="btn btn-primary btn-circle" title="{{ __('New Request') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Start') }}</th>
                            <th>{{ __('End') }}</th>
                            <th>{{ __('Duration (min)') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($personnelRequests as $req)
                            <tr>
                                <td>{{ $req->request_type->label() }}</td>
                                <td>{{ formatDateTime($req->start_date) }}</td>
                                <td>{{ formatDateTime($req->end_date) }}</td>
                                <td>
                                    @if ($req->start_date && $req->end_date)
                                        @php
                                            $totalMinutes = $req->start_date->diffInMinutes($req->end_date);
                                            $hours = intdiv($totalMinutes, 60);
                                            $minutes = $totalMinutes % 60;
                                        @endphp
                                        {{ str_pad($hours, 2, '0', STR_PAD_LEFT) }}:{{ str_pad($minutes, 2, '0', STR_PAD_LEFT) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($req->status === 'approved')
                                        <span class="badge badge-success badge-sm">{{ __('Approved') }}</span>
                                    @elseif ($req->status === 'rejected')
                                        <span class="badge badge-error badge-sm">{{ __('Rejected') }}</span>
                                    @else
                                        <span class="badge badge-warning badge-sm">{{ __('Pending') }}</span>
                                    @endif
                                </td>
                                <td class="max-w-xs truncate" title="{{ $req->reason }}">
                                    {{ $req->reason ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-gray-500">
                                    {{ __('No requests found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $personnelRequests->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
