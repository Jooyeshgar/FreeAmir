<x-app-layout :title="__('My Requests')">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <x-show-message-bags />

            {{-- Tabs --}}
            <div role="tablist" class="tabs tabs-lift tabs-lg mb-4">

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
                            <th>{{ __('Entry Time') }}</th>
                            <th>{{ __('Exit Time') }}</th>
                            <th>{{ __('Duration') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($personnelRequests as $req)
                            <tr>
                                <td>{{ $req->request_type->label() }}</td>
                                <td dir="ltr">{{ formatDateTime($req->start_date) }}</td>
                                <td dir="ltr">{{ formatDateTime($req->end_date) }}</td>
                                <td>
                                    @if ($req->start_date && $req->end_date)
                                        @php
                                            $totalMinutes = $req->start_date->diffInMinutes($req->end_date);
                                            $hours = intdiv($totalMinutes, 60);
                                            $minutes = $totalMinutes % 60;
                                        @endphp
                                        {{ convertToFarsi(str_pad($hours, 2, '0', STR_PAD_LEFT)) }}:{{ convertToFarsi(str_pad($minutes, 2, '0', STR_PAD_LEFT)) }}
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
                                <td>
                                    @if ($req->status === 'pending')
                                        <a href="{{ route('employee-portal.personnel-requests.edit', ['tab' => $tab, 'personnel_request' => $req->id]) }}" class="btn btn-sm btn-info">
                                            {{ __('Edit') }}
                                        </a>
                                        <form action="{{ route('employee-portal.personnel-requests.destroy', ['tab' => $tab, 'personnel_request' => $req->id]) }}" method="POST" class="inline-block m-0"
                                            onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                        </form>
                                    @else
                                        <button class="btn btn-sm btn-disabled">{{ __('Edit') }}</button>
                                        <button class="btn btn-sm btn-disabled">{{ __('Delete') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-gray-500">
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
