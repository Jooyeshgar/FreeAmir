<x-app-layout :title="__('Product Inventory Turnover')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="card-title">{{ __('Product Inventory Turnover') }}</h1>
                @can('reports.inventory-turnover.pdf')
                    <x-export-delivery-choice id="inventory-turnover-pdf-delivery" export="inventory_turnover_pdf"
                        :filters="request()->query()" :label="__('PDF')" :title="__('Receive PDF')" />
                @endcan
            </div>

            <form action="{{ route('reports.inventory-turnover') }}" method="get"
                class="mt-3 flex flex-wrap items-end gap-2">
                <div class="w-40 [&_.input]:input-sm">
                    <x-text-input data-jdp title="{{ __('Start date') }}" input_name="start_date"
                        placeholder="{{ __('Start date') }}" readonly label_class="text-sm"
                        input_value="{{ old('start_date') ?? convertToJalali($filters['start_date'], true) }}"
                        label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
                </div>
                <div class="w-40 [&_.input]:input-sm">
                    <x-text-input data-jdp title="{{ __('End date') }}" input_name="end_date"
                        placeholder="{{ __('End date') }}" readonly label_class="text-sm"
                        input_value="{{ old('end_date') ?? convertToJalali($filters['end_date'], true) }}"
                        label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
                </div>
                @php
                    $selectedProductGroupId = (string) ($filters['product_group'] ?? '');
                    $selectedProductGroupValue = $selectedProductGroupId !== '' ? "group-{$selectedProductGroupId}" : '';
                    $productGroupOptions = collect([(object) ['id' => '', 'name' => __('All Groups')]])->concat($productGroups);
                    $selectedWarehouseId = (string) ($filters['warehouse_id'] ?? '');
                    $selectedWarehouseValue = $selectedWarehouseId !== '' ? "warehouse-{$selectedWarehouseId}" : '';
                    $warehouseOptions = collect([(object) ['id' => '', 'name' => __('All Warehouses')]])->concat($warehouses);
                @endphp
                <div class="form-control w-48" x-data="{
                    productGroupId: @js($selectedProductGroupId),
                    selectedProductGroup: @js($selectedProductGroupValue),
                }">
                    <span class="text-sm text-gray-500">{{ __('Product group') }}</span>
                    <x-select-box :options="[['headerGroup' => 'group', 'options' => $productGroupOptions]]"
                        class="[&>button]:select-sm [&>button]:!h-8 [&>button]:!min-h-8"
                        x-model="selectedProductGroup" x-init="selectedValue = selectedProductGroup"
                        placeholder="{{ __('All Groups') }}"
                        @selected="productGroupId = String($event.detail.id)" />
                    <input type="hidden" name="product_group" x-bind:value="productGroupId">
                </div>
                <div class="form-control w-48" x-data="{
                    warehouseId: @js($selectedWarehouseId),
                    selectedWarehouse: @js($selectedWarehouseValue),
                }">
                    <span class="text-sm text-gray-500">{{ __('Warehouse') }}</span>
                    <x-select-box :options="[['headerGroup' => 'warehouse', 'options' => $warehouseOptions]]"
                        class="[&>button]:select-sm [&>button]:!h-8 [&>button]:!min-h-8"
                        x-model="selectedWarehouse" x-init="selectedValue = selectedWarehouse"
                        placeholder="{{ __('All Warehouses') }}"
                        @selected="warehouseId = String($event.detail.id)" />
                    <input type="hidden" name="warehouse_id" x-bind:value="warehouseId">
                </div>
                <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                <a href="{{ route('reports.inventory-turnover') }}"
                    class="btn btn-sm btn-outline">{{ __('Clear') }}</a>
            </form>

            <div class="mt-4 overflow-auto rounded-md border border-gray-300">
                <table class="table table-sm w-full text-xs [&_th]:text-xs [&_td]:text-xs">
                    <thead class="text-xs">
                        <tr class="bg-base-200">
                            <th rowspan="2" class="text-center align-middle border-r border-gray-300">{{ __('Product code') }}</th>
                            <th rowspan="2" class="text-center align-middle border-r border-gray-300">
                                {{ __('Product name') }}</th>
                            <th colspan="2" class="text-center border-r border-gray-300">
                                {{ __('Remaining from past') }}</th>
                            <th colspan="2" class="text-center border-r border-gray-300">{{ __('Imported') }}</th>
                            <th colspan="2" class="text-center border-r border-gray-300">{{ __('Exported') }}</th>
                            <th colspan="2" class="text-center border-r border-gray-300">{{ __('Remaining') }}</th>
                        </tr>
                        <tr class="bg-base-200">
                            @foreach (range(1, 4) as $group)
                                <th class="text-center border-r border-gray-300">{{ __('Quantity') }}</th>
                                <th class="text-center border-r border-gray-300">{{ __('Total Amount') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-base-300">
                                <td class="text-center border-r border-gray-300">{{ formatCode($row['subject_code']) }}</td>
                                <td class="border-r border-gray-300">{{ $row['product_name'] }}</td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['opening_quantity']) }}</td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['opening_balance']) }}</td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['imported_quantity']) }}</td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['imported_balance']) }}</td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['exported_quantity']) }}</td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['exported_balance']) }}</td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['remaining_quantity']) }}
                                </td>
                                <td class="border-r border-gray-300">{{ formatNumber($row['remaining_balance']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-4 text-center text-gray-500">
                                    {{ __('No products found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="text-xs">
                        <tr class="bg-base-200 ">
                            <td colspan="2" class="text-center border-gray-300">{{ __('Total') }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['opening_quantity']) }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['opening_balance']) }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['imported_quantity']) }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['imported_balance']) }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['exported_quantity']) }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['exported_balance']) }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['remaining_quantity']) }}</td>
                            <td class="border-r border-gray-300">{{ formatNumber($totals['remaining_balance']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce

</x-app-layout>
