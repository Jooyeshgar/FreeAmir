<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $product->name }}</h2>
            <div class="flex flex-wrap gap-2 mt-2">
                @if ($product->productgroup)
                    <a href="{{ route('products.index', ['product_group_id' => $product->productgroup->id]) }}"
                        class="badge badge-lg badge-primary gap-2 hover:badge-primary hover:brightness-110 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        {{ $product->productgroup->name }}
                    </a>
                @endif

                @if ($product->location)
                    <span class="badge badge-lg badge-secondary gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ $product->location }}
                    </span>
                @endif

                @if ($product->code)
                    <span class="badge badge-lg badge-accent gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                        {{ $product->code }}
                    </span>
                @endif

                @if ($product->productWebsites && $product->productWebsites->count() > 0)
                    @foreach ($product->productWebsites as $website)
                        @php
                            $domain = parse_url($website->link, PHP_URL_HOST) ?? $website->link; // Extract domain from URL
                            $domain = preg_replace('/^www\./i', '', $domain); //  Remove 'www.' if present
                        @endphp
                        <a href="{{ $website->link }}" target="_blank" rel="noopener noreferrer"
                            class="badge badge-lg badge-info gap-2 hover:badge-info hover:brightness-110 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                            </svg>
                            {{ $domain }}
                        </a>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-3">
                <x-stat-card :title="__('Quantity')" :value="formatNumber($product->quantity ?? 0)" :description="__('In Stock')" type="success" icon="quantity" />
                <x-stat-card :title="__('Quantity warning')" :value="formatNumber($product->quantity_warning ?? 0)" :description="__('Alert Level')" type="warning" icon="warning" />
                <x-stat-card :title="__('Oversell')" :value="formatNumber($product->oversell ?? 0)" :description="__('Allowed')" type="error" icon="oversell" />
                <x-stat-card :title="__('VAT')" :value="formatNumber($product->vat ?? 0) . '%'" :description="__('Tax Rate')" type="info" icon="vat" />
            </div>

            <div class="divider text-lg font-semibold">{{ __('Pricing Information') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-3">
                <x-stat-card :title="__('Selling Price')" :value="formatNumber($product->selling_price ?? 0)" type="info" />
                <x-stat-card :title="__('Average Cost')" :value="formatNumber($product->average_cost ?? 0)" type="info" />
                <x-stat-card :title="__('Last Cost Of Goods')" :value="formatNumber($product->lastCOG ?? 0)" type="info" />
                <x-stat-card :title="__('Sales profit')" :value="formatNumber($product->salesProfit ?? 0)" type="success" />
                <x-stat-card :title="__('Discount Formula')" :value="$product->discount_formula ?? __('None')" type="info" />
            </div>
            @can('reports.ledger')
                <div class="divider text-lg font-semibold">{{ __('Account Subjects') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                    <x-stat-card-link :title="__('Income Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->incomeSubject))" :link="route('transactions.index', ['subject_id' => $product->incomeSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="success" icon="income" />
                    <x-stat-card-link :title="__('COGS Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->cogsSubject))" :link="route('transactions.index', ['subject_id' => $product->cogsSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="error" icon="cogs" />
                    <x-stat-card-link :title="__('Inventory Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->inventorySubject))" :link="route('transactions.index', ['subject_id' => $product->inventorySubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="info" icon="inventory" />
                    <x-stat-card-link :title="__('Sales Returns Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->salesReturnsSubject))" :link="route('transactions.index', ['subject_id' => $product->salesReturnsSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="warning" icon="returns" />
                </div>
            @endcan
            @if (isset($product->description) && $product->description)
                <div class="divider text-lg font-semibold">{{ __('Description') }}</div>
                <div class="alert bg-base-200 shadow-sm mb-6">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>{{ $product->description }}</span>
                    </div>
                </div>
            @endif

            <div class="divider text-lg font-semibold">{{ __('Transaction History') }}</div>
            <div class="overflow-x-auto shadow-lg rounded-lg">
                <table class="table table-zebra w-full">
                    <thead class="bg-base-300">
                        <tr>
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Buy') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Sell') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Buy Unit Price') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Ancillary Cost') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Sell Unit Price') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('OFF') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Remaining') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($historyItems as $item)
                            <tr class="hover {{ !$item->invoice->status->isApproved() ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3">
                                    <span class="badge badge-ghost">
                                        {{ formatDate($item->invoice->date) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY)
                                        <a href="{{ route('invoices.show', $item->invoice_id) }}"
                                            class="badge badge-success gap-2 hover:badge-success hover:brightness-110 transition-all">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                            {{ formatNumber($item->quantity) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::SELL)
                                        <a href="{{ route('invoices.show', $item->invoice_id) }}"
                                            class="badge badge-info gap-2 hover:badge-info hover:brightness-110 transition-all">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                            </svg>
                                            {{ formatNumber($item->quantity) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center font-semibold">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY)
                                        {{ formatNumber($item->unit_price) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $ancillaryCost = 0;
                                        if ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY) {
                                            $ancillaryCost =
                                                $item->invoice->ancillaryCosts->sum(function ($ac) {
                                                    return $ac->items->sum('amount');
                                                }) / $item->quantity;
                                        }
                                    @endphp
                                    @if ($ancillaryCost > 0)
                                        {{ formatNumber($ancillaryCost) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center font-semibold">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::SELL)
                                        {{ formatNumber($item->unit_price) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($item->unit_discount > 0)
                                        <span class="badge badge-warning">{{ formatNumber($item->unit_discount) }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if ($item->invoice->status->isApproved())
                                        <span
                                            class="badge {{ $item->remaining < 0 ? 'badge-error' : ($item->remaining < $product->quantity_warning ? 'badge-warning' : 'badge-success') }} font-bold">
                                            {{ formatNumber($item->quantity_at + $item->quantity) }}</span>
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    {{ __('No transactions found') }}
                                </td>
                            </tr>
                        @endforelse
                        @if ($historyItems->hasPages())
                            <tr class="bg-base-300 font-semibold">
                                <td colspan="7" class="px-4 py-3 text-right">
                                    {{ __('Balance from previous page') }}
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $balanceFromPrevious = 0;
                                        if ($historyItems->currentPage() < $historyItems->lastPage() && $historyItems->isNotEmpty()) {
                                            $lastItem = $historyItems->last();
                                            $balanceFromPrevious = $lastItem->quantity;
                                        }
                                        if ($historyItems->currentPage() == 1) {
                                            $balanceFromPrevious = $product->quantity;
                                        }
                                    @endphp
                                    <span
                                        class="badge {{ $balanceFromPrevious < 0 ? 'badge-error' : ($balanceFromPrevious < $product->quantity_warning ? 'badge-warning' : 'badge-success') }} font-bold">
                                        {{ formatNumber($balanceFromPrevious) }}
                                    </span>
                                </td>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            @if ($historyItems->hasPages())
                <div class="mt-6 flex justify-center">
                    {{ $historyItems->links() }}
                </div>
            @endif

            <div class="card-actions justify-between mt-8">
                <a href="{{ route('products.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <a href="{{ route('products.edit', $product) }}" class="btn btn-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    {{ __('Edit') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
