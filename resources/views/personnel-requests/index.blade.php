<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Personnel Requests') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            {{-- Tabs --}}
            <div role="tablist" class="tabs tabs-lifted tabs-lg mb-4">

                <a role="tab" href="{{ route('personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'leaves'])) }}"
                    class="tab {{ $tab === 'leaves' ? 'tab-active' : '' }}">
                    {{ __('Leaves') }}
                    @if ($pendingCounts['leaves'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['leaves'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'missions'])) }}"
                    class="tab {{ $tab === 'missions' ? 'tab-active' : '' }}">
                    {{ __('Missions') }}
                    @if ($pendingCounts['missions'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['missions'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'work_orders'])) }}"
                    class="tab {{ $tab === 'work_orders' ? 'tab-active' : '' }}">
                    {{ __('Work Orders') }}
                    @if ($pendingCounts['work_orders'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['work_orders'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'other'])) }}"
                    class="tab {{ $tab === 'other' ? 'tab-active' : '' }}">
                    {{ __('Other') }}
                    @if ($pendingCounts['other'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['other'] }}</span>
                    @endif
                </a>

            </div>

            {{-- Filters --}}
            <div class="flex flex-wrap items-end justify-between gap-3">
                <form action="{{ route('personnel-requests.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}" />

                    <select name="employee_id" class="select select-bordered select-sm">
                        <option value="">{{ __('All Employees') }}</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->first_name }} {{ $employee->last_name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="status" class="select select-bordered select-sm">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>
                            {{ __('Pending') }}
                        </option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>
                            {{ __('Approved') }}
                        </option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>
                            {{ __('Rejected') }}
                        </option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-neutral">
                        {{ __('Search') }}
                    </button>
                    <a href="{{ route('personnel-requests.index', ['tab' => $tab]) }}" class="btn btn-sm btn-ghost">
                        {{ __('Reset') }}
                    </a>
                </form>

                @can('hr.personnel-requests.create')
                    <a href="{{ route('personnel-requests.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Personnel Request') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </a>
                @endcan
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto mt-4">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Request Type') }}</th>
                            <th>{{ __('Start Date') }}</th>
                            <th>{{ __('End Date') }}</th>
                            <th>{{ __('Duration (min)') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($personnelRequests as $personnelRequest)
                            <tr class="{{ $personnelRequest->status === 'pending' ? 'bg-warning/10' : '' }}">
                                <td>
                                    {{ $personnelRequest->employee?->first_name }}
                                    {{ $personnelRequest->employee?->last_name }}
                                </td>
                                <td>{{ $personnelRequest->request_type->label() }}</td>
                                <td>{{ $personnelRequest->start_date->format('Y-m-d H:i') }}</td>
                                <td>{{ $personnelRequest->end_date->format('Y-m-d H:i') }}</td>
                                <td>{{ $personnelRequest->duration_minutes }}</td>
                                <td>
                                    @if ($personnelRequest->status === 'pending')
                                        <span class="badge badge-warning">{{ __('Pending') }}</span>
                                    @elseif ($personnelRequest->status === 'approved')
                                        <span class="badge badge-success">{{ __('Approved') }}</span>
                                    @else
                                        <span class="badge badge-error">{{ __('Rejected') }}</span>
                                    @endif
                                </td>
                                <td class="flex gap-2">
                                    @can('hr.personnel-requests.edit')
                                        <a href="{{ route('personnel-requests.edit', $personnelRequest) }}" class="btn btn-sm btn-info">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                    @can('hr.personnel-requests.delete')
                                        <form action="{{ route('personnel-requests.destroy', $personnelRequest) }}" method="POST" class="inline-block"
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
                                <td colspan="7" class="text-center py-4 text-gray-500">
                                    {{ __('No personnel requests found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $personnelRequests->links() !!}

        </div>
    </div>
</x-app-layout>
