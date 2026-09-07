<x-app-layout :title="__('Warehouses')">
    <x-show-message-bags />

    <div class="flex flex-col gap-4 px-1 pb-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Warehouses') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your warehouses and stock locations') }}</p>
        </div>

        <div class="grid w-full grid-cols-2 gap-2 md:flex md:w-auto md:justify-end">
            <a class="btn btn-sm btn-primary shadow-sm" href="{{ route('warehouses.create') }}">{{ __('Create warehouse') }}</a>
            <a class="btn btn-sm btn-outline" href="{{ route('warehouses.transfer') }}">{{ __('Transfer Product') }}</a>
            @can('warehouses.transfer-history')
                <a class="btn btn-sm btn-info" href="{{ route('warehouses.transfer-history') }}">{{ __('Transfer History') }}</a>
            @endcan
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
        <div class="card-body p-0">
            <div class="flex flex-col gap-3 border-b border-base-200 px-4 py-4 lg:flex-row lg:items-center lg:justify-between lg:px-5">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Warehouse List') }}</h2>
                    <span class="badge badge-ghost">
                        {{ localizeNumber($warehouses->total()) }} {{ __('records') }}
                    </span>
                </div>

                <form action="{{ route('warehouses.index') }}" method="GET" class="grid w-full grid-cols-2 items-end gap-2 sm:grid-cols-4 lg:w-auto lg:grid-cols-[12.5rem_5rem_7.5rem_auto]">
                    <div class="relative w-full [&_.input]:input-sm">
                        <x-input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Warehouse name') }}" />
                    </div>

                    <div class="relative w-full [&_.input]:input-sm">
                        <x-input type="text" name="code" value="{{ request('code') }}" placeholder="{{ __('Code') }}" />
                    </div>

                    <div class="relative w-full [&_.input]:input-sm">
                        <x-input type="number" min="0" name="Warehouse_inventory" value="{{ request('Warehouse_inventory', request('warehouse_inventory')) }}" placeholder="{{ __('Warehouse inventory') }}" />
                    </div>

                    <button type="submit" class="btn btn-sm btn-neutral w-full">{{ __('Search') }}</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="table min-w-[52rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Warehouse inventory') }}</th>
                            <th>{{ __('Sum Warehouse products average cost') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($warehouses as $warehouse)
                            <tr>
                                <td>
                                    <a href="{{ route('warehouses.show', $warehouse) }}">{{ $warehouse->name }}</a>
                                </td>
                                <td>{{ $warehouse->code ?: '—' }}</td>
                                <td>{{ formatNumber($warehouse->stocks_sum_quantity ?? 0) }}</td>
                                <td>{{ formatNumber($warehouse->stocks_sum_average_cost ?? 0) }}</td>
                                <td class="flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-ghost" href="{{ route('warehouses.show', $warehouse) }}">{{ __('View') }}</a>
                                    <a class="btn btn-sm btn-info" href="{{ route('warehouses.edit', $warehouse) }}">{{ __('Edit') }}</a>
                                    @php
                                        $deleteDisabledReason = match (true) {
                                            $warehouseCount <= 1 => __('At least one warehouse must remain.'),
                                            $warehouse->positive_stock_count > 0 => __('A warehouse with stock cannot be deleted.'),
                                            ($warehouse->outgoing_transfer_count + $warehouse->incoming_transfer_count) > 0 => __('A warehouse with transfer history cannot be deleted.'),
                                            default => null,
                                        };
                                    @endphp
                                    @if ($deleteDisabledReason)
                                        <span class="lg:tooltip" data-tip="{{ $deleteDisabledReason }}">
                                            <button type="button" class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled title="{{ $deleteDisabledReason }}">{{ __('Delete') }}</button>
                                        </span>
                                    @else
                                        <form class="inline" method="POST" action="{{ route('warehouses.destroy', $warehouse) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($warehouses->hasPages())
                <div class="border-t border-base-200 px-4 py-4 sm:px-5">{{ $warehouses->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
