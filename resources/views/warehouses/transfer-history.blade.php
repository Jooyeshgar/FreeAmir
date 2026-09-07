<x-app-layout :title="__('Transfer History')">
    <x-show-message-bags />

    <div class="flex flex-col gap-4 px-1 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Transfer History') }}</h1>
            <p class="mt-0.5 text-sm text-base-content/50">{{ __('Review product transfers between warehouses') }}</p>
        </div>

        <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap">
            <a href="{{ route('warehouses.transfer') }}" class="btn btn-sm btn-primary w-full sm:w-auto">{{ __('Transfer Product') }}</a>
            <a href="{{ route('warehouses.index') }}" class="btn btn-sm btn-outline w-full sm:w-auto">{{ __('Warehouses') }}</a>
        </div>
    </div>

    <div class="card mx-1 mb-6 border border-base-200 bg-base-100 shadow-sm">
        <div class="card-body p-0">
            <div class="border-b border-base-200 px-4 py-4 sm:px-5">
                <form action="{{ route('warehouses.transfer-history') }}" method="GET" class="grid grid-cols-1 items-end gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="relative w-full [&_.input]:input-sm">
                        <x-input type="text" name="product_name" value="{{ request('product_name') }}" title="{{ __('Product') }}" placeholder="{{ __('Product Name') }}" />
                    </div>

                    <label>
                        <span class="label-text text-sm">{{ __('From warehouse') }}</span>
                        <select name="from_warehouse_id" class="select select-bordered select-sm mt-1 w-full">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($warehouses as $warehouseOption)
                                <option value="{{ $warehouseOption->id }}" @selected((string) request('from_warehouse_id') === (string) $warehouseOption->id)>{{ $warehouseOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span class="label-text text-sm">{{ __('To warehouse') }}</span>
                        <select name="to_warehouse_id" class="select select-bordered select-sm mt-1 w-full">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($warehouses as $warehouseOption)
                                <option value="{{ $warehouseOption->id }}" @selected((string) request('to_warehouse_id') === (string) $warehouseOption->id)>{{ $warehouseOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="[&_.input]:input-sm">
                        <x-date-picker name="date_from" id="date_from" title="{{ __('From Date') }}" :value="request('date_from')" />
                    </div>

                    <div class="[&_.input]:input-sm">
                        <x-date-picker name="date_to" id="date_to" title="{{ __('To Date') }}" :value="request('date_to')" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                        <a href="{{ route('warehouses.transfer-history') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>

            <div class="flex flex-wrap items-center gap-3 px-4 py-4 sm:px-5">
                <h2 class="text-base font-bold text-base-content">{{ __('Transfer History') }}</h2>
                <span class="badge badge-ghost">{{ localizeNumber($transfers->total()) }} {{ __('records') }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="table min-w-[64rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('From warehouse') }}</th>
                            <th>{{ __('To warehouse') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Unit') }}</th>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            <tr class="hover:bg-base-200/50">
                                <td class="whitespace-nowrap">{{ formatDate($transfer->transferred_at) }}</td>
                                <td>
                                    @if ($transfer->product)
                                        <a class="font-bold text-primary hover:underline" href="{{ route('products.show', $transfer->product) }}">{{ $transfer->product->name }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $transfer->fromWarehouse?->name ?: '—' }}</td>
                                <td>{{ $transfer->toWarehouse?->name ?: '—' }}</td>
                                <td>{{ formatNumber($transfer->quantity) }}</td>
                                <td>{{ formatNumber($transfer->unit_cost) }}</td>
                                <td>{{ $transfer->transferor?->name ?: '—' }}</td>
                                <td class="max-w-xs whitespace-normal">{{ $transfer->description ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-base-content/50">{{ __('No transfer history found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($transfers->hasPages())
                <div class="border-t border-base-200 px-5 py-4">{{ $transfers->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
