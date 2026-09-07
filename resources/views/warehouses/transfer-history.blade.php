<x-app-layout :title="__('Transfer History')">
    <x-show-message-bags />

    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Transfer History') }}</h1>
            <p class="mt-0.5 text-sm text-base-content/50">{{ __('Review product transfers between warehouses') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('warehouses.transfer') }}" class="btn btn-sm btn-primary">{{ __('Transfer Product') }}</a>
            <a href="{{ route('warehouses.index') }}" class="btn btn-sm btn-outline">{{ __('Warehouses') }}</a>
        </div>
    </div>

    <div class="card mx-1 mb-6 border border-base-200 bg-base-100 shadow-sm">
        <div class="card-body p-0">
            <div class="border-b border-base-200 px-5 py-4">
                <form action="{{ route('warehouses.transfer-history') }}" method="GET" class="flex flex-wrap items-end gap-3">
                    <label class="w-56 max-w-full">
                        <span class="label-text text-sm">{{ __('Product') }}</span>
                        <select name="product_id" class="select select-bordered select-sm mt-1 w-full">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="w-48 max-w-full">
                        <span class="label-text text-sm">{{ __('From warehouse') }}</span>
                        <select name="from_warehouse_id" class="select select-bordered select-sm mt-1 w-full">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($warehouses as $warehouseOption)
                                <option value="{{ $warehouseOption->id }}" @selected((string) request('from_warehouse_id') === (string) $warehouseOption->id)>{{ $warehouseOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="w-48 max-w-full">
                        <span class="label-text text-sm">{{ __('To warehouse') }}</span>
                        <select name="to_warehouse_id" class="select select-bordered select-sm mt-1 w-full">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($warehouses as $warehouseOption)
                                <option value="{{ $warehouseOption->id }}" @selected((string) request('to_warehouse_id') === (string) $warehouseOption->id)>{{ $warehouseOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="w-32 max-w-full [&_.input]:input-sm">
                        <x-date-picker name="date_from" id="date_from" title="{{ __('From Date') }}" :value="request('date_from')" />
                    </div>

                    <div class="w-32 max-w-full [&_.input]:input-sm">
                        <x-date-picker name="date_to" id="date_to" title="{{ __('To Date') }}" :value="request('date_to')" />
                    </div>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                    <a href="{{ route('warehouses.transfer-history') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                </form>
            </div>

            <div class="flex items-center gap-3 px-5 py-4">
                <h2 class="text-base font-bold text-base-content">{{ __('Transfer History') }}</h2>
                <span class="badge badge-ghost">{{ localizeNumber($transfers->total()) }} {{ __('records') }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
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
