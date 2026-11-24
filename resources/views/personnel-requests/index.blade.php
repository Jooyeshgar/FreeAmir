<x-app-layout :title="__('Personnel Requests')">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body p-4 sm:p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-base-content">{{ __('Personnel Requests') }}</h1>
                <x-user-guide-link source="hr/personnel-requests/personnel-requests.md" />
            </div>

            {{-- Tabs --}}
            <div role="tablist" class="flex justify-around tabs tabs-lifted tabs-lg mb-4">

                <a role="tab" href="{{ route('hr.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'leaves'])) }}"
                    class="tab min-h-10 whitespace-nowrap md:flex-1 {{ $tab === 'leaves' ? 'tab-active' : '' }}">
                    {{ __('Leaves') }}
                    @if ($pendingCounts['leaves'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['leaves'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('hr.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'missions'])) }}"
                    class="tab min-h-10 whitespace-nowrap md:flex-1 {{ $tab === 'missions' ? 'tab-active' : '' }}">
                    {{ __('Missions') }}
                    @if ($pendingCounts['missions'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['missions'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('hr.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'work_orders'])) }}"
                    class="tab min-h-10 whitespace-nowrap md:flex-1 {{ $tab === 'work_orders' ? 'tab-active' : '' }}">
                    {{ __('Work Orders') }}
                    @if ($pendingCounts['work_orders'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['work_orders'] }}</span>
                    @endif
                </a>

                <a role="tab" href="{{ route('hr.personnel-requests.index', array_merge(request()->except('tab', 'page'), ['tab' => 'other'])) }}"
                    class="tab min-h-10 whitespace-nowrap md:flex-1 {{ $tab === 'other' ? 'tab-active' : '' }}">
                    {{ __('Other') }}
                    @if ($pendingCounts['other'] > 0)
                        <span class="badge badge-warning badge-sm ms-1">{{ $pendingCounts['other'] }}</span>
                    @endif
                </a>

            </div>

            {{-- Filters --}}
            <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-end">
                <form action="{{ route('hr.personnel-requests.index') }}" method="GET" class="flex min-w-0 flex-wrap items-end gap-2">
                    <x-input name="tab" value="{{ $tab }}" hidden />

                    <select name="employee_id" class="select select-sm w-44 shrink-0">
                        <option value="">{{ __('All Employees') }}</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->first_name }} {{ $employee->last_name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="status" class="select select-sm w-32 shrink-0">
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

                    <div class="w-32 shrink-0 [&_.input]:input-sm">
                        <x-date-picker name="date_from" id="date_from" :title="__('From Date')" :value="request('date_from')" readonly />
                    </div>
                    <div class="w-32 shrink-0 [&_.input]:input-sm">
                        <x-date-picker name="date_to" id="date_to" :title="__('To Date')" :value="request('date_to')" readonly />
                    </div>

                    <button type="submit" class="btn btn-sm btn-neutral shrink-0">{{ __('Search') }}</button>
                </form>

                @can('hr.personnel-requests.create')
                    <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('hr.personnel-requests.create', ['tab' => $tab]) }}" class="btn btn-primary btn-circle shrink-0 self-end">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </x-closed-year-action-link>
                @endcan
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto mt-4">
                <table class="table min-w-[64rem] w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Request Type') }}</th>
                            <th>{{ __('Start Date') }}</th>
                            <th>{{ __('End Date') }}</th>
                            <th>{{ __('Duration') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($personnelRequests as $personnelRequest)
                            <tr class="{{ $personnelRequest->status?->isPending() ? 'bg-warning/10' : '' }}">
                                <td class="whitespace-nowrap">
                                    {{ $personnelRequest->employee?->first_name }}
                                    {{ $personnelRequest->employee?->last_name }}
                                </td>
                                <td>{{ $personnelRequest->request_type->label() }}</td>
                                <td dir="ltr">{{ formatDateTime($personnelRequest->start_date) }}</td>
                                <td dir="ltr">{{ formatDateTime($personnelRequest->end_date) }}</td>
                                <td class="whitespace-nowrap" dir="ltr">
                                    @php($durationMinutes = $personnelRequest->durationMinutes())
                                    @if ($durationMinutes !== null)
                                        {{ localizeNumber(sprintf('%02d:%02d', intdiv($durationMinutes, 60), $durationMinutes % 60)) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($personnelRequest->status?->isPending())
                                        <span class="badge badge-warning whitespace-nowrap">{{ __('Pending') }}</span>
                                    @elseif ($personnelRequest->status?->isApproved())
                                        <span class="badge badge-success whitespace-nowrap">{{ __('Approved') }}</span>
                                    @else
                                        <span class="badge badge-error whitespace-nowrap">{{ __('Rejected') }}</span>
                                    @endif
                                    {{ $personnelRequest->approvedBy ? __('by :name', ['name' => $personnelRequest->approvedBy->name]) : '' }}
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="flex flex-nowrap items-center gap-2">
                                    @can('hr.personnel-requests.approve')
                                        @if ($personnelRequest->status?->isPending() || $personnelRequest->status?->isRejected())
                                            <form action="{{ route('hr.personnel-requests.approve', $personnelRequest) }}" method="POST" class="inline-block mb-0">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" @disabled($fiscalYearClosed) class="text-lg h-8 p-1 {{ $fiscalYearClosed ? 'opacity-60' : '' }}" title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : __('Approve') }}">
                                                    👍
                                                </button>
                                            </form>
                                        @endif
                                        @if ($personnelRequest->status?->isPending() || $personnelRequest->status?->isApproved())
                                            <form action="{{ route('hr.personnel-requests.reject', $personnelRequest) }}" method="POST" class="inline-block mb-0">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" @disabled($fiscalYearClosed) class="text-lg h-8 p-1 {{ $fiscalYearClosed ? 'opacity-60' : '' }}" title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : __('Reject') }}">
                                                    👎
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                    @if($personnelRequest->status?->isPending())
                                        @can('hr.personnel-requests.edit')
                                            <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('hr.personnel-requests.edit', ['tab' => $tab, 'personnel_request' => $personnelRequest->id]) }}" class="btn btn-sm btn-info">
                                                {{ __('Edit') }}
                                            </x-closed-year-action-link>
                                        @endcan
                                    @else
                                        <button type="submit" class="btn btn-sm btn-disabled">{{ __('Edit') }}</button>
                                    @endif
                                    @if(! $personnelRequest->status?->isApproved())
                                        @can('hr.personnel-requests.delete')
                                            <form action="{{ route('hr.personnel-requests.destroy', ['tab' => $tab, 'personnel_request' => $personnelRequest->id]) }}" method="POST" class="inline-block"
                                                onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" @disabled($fiscalYearClosed) class="btn btn-sm btn-error {{ $fiscalYearClosed ? 'opacity-60' : '' }}" title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : __('Delete') }}">
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endcan
                                    @else
                                        <button type="submit" class="btn btn-sm btn-disabled">{{ __('Delete') }}</button>
                                    @endif
                                    </div>
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
                    @if ($pageDurationMinutes !== null)
                        <tfoot>
                            <tr class="bg-base-200 text-base-content">
                                <th colspan="4" scope="row">{{ __('Total') }}</th>
                                <td class="whitespace-nowrap" dir="ltr">{{ localizeNumber(sprintf('%02d:%02d', intdiv($pageDurationMinutes, 60), $pageDurationMinutes % 60)) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            {!! $personnelRequests->links() !!}

        </div>
    </div>
</x-app-layout>
