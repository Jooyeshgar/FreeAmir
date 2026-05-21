@php
    $quickLinks = collect([
        ['perm' => 'products.index', 'label' => __('Products'), 'href' => route('products.index'), 'icon' => 'M3 7l9-4 9 4-9 4-9-4zm0 0v10l9 4 9-4V7'],
        ['perm' => 'services.index', 'label' => __('Services'), 'href' => route('services.index'), 'icon' => 'M9 17v-2a4 4 0 014-4h6m0 0l-3-3m3 3l-3 3'],
        ['perm' => 'customers.index', 'label' => __('Customer List'), 'href' => route('customers.index'), 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 014-4h4a4 4 0 014 4v2zm4-12a4 4 0 11-8 0 4 4 0 018 0z'],
        ['perm' => 'bank-accounts.index', 'label' => __('Bank Accounts'), 'href' => route('bank-accounts.index'), 'icon' => 'M3 10h18M5 6h14v12H5z'],
        ['perm' => 'documents.create', 'label' => __('Document Issuance'), 'href' => route('documents.create'), 'icon' => 'M12 4v16m8-8H4'],
        ['perm' => 'reports.ledger', 'label' => __('Ledger Report'), 'href' => route('reports.ledger'), 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['perm' => 'invoices.create', 'label' => __('Buy Invoice Issuance'), 'href' => route('invoices.create', ['invoice_type' => 'buy']), 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4'],
        ['perm' => 'invoices.create', 'label' => __('Sell Invoice Issuance'), 'href' => route('invoices.create', ['invoice_type' => 'sell']), 'icon' => 'M5 13l4 4L19 7'],
        ['perm' => 'management.configs.index', 'label' => __('Configs'), 'href' => url('management/configs'), 'icon' => 'M10.325 4.317a1 1 0 011.35 0l1.18 1.18a1 1 0 001.06.24l1.575-.525a1 1 0 011.265.74l.34 1.645a1 1 0 00.78.78l1.645.34a1 1 0 01.74 1.265l-.525 1.575a1 1 0 00.24 1.06l1.18 1.18a1 1 0 010 1.35l-1.18 1.18a1 1 0 00-.24 1.06l.525 1.575a1 1 0 01-.74 1.265l-1.645.34a1 1 0 00-.78.78l-.34 1.645a1 1 0 01-1.265.74l-1.575-.525a1 1 0 00-1.06.24l-1.18 1.18a1 1 0 01-1.35 0l-1.18-1.18a1 1 0 00-1.06-.24l-1.575.525a1 1 0 01-1.265-.74l-.34-1.645a1 1 0 00-.78-.78l-1.645-.34a1 1 0 01-.74-1.265l.525-1.575a1 1 0 00-.24-1.06l-1.18-1.18a1 1 0 010-1.35l1.18-1.18a1 1 0 00.24-1.06l-.525-1.575a1 1 0 01.74-1.265l1.645-.34a1 1 0 00.78-.78l.34-1.645a1 1 0 011.265-.74l1.575.525a1 1 0 001.06-.24l1.18-1.18z'],
    ])->filter(fn ($link) => auth()->user()->can($link['perm']));
@endphp

@if ($quickLinks->isNotEmpty())
    <section class="card border border-base-300 bg-base-100/90 shadow-sm">
        <div class="card-body p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="card-title text-base">{{ __('Quick Access') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Frequently used shortcuts') }}</p>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ($quickLinks as $link)
                    <a href="{{ $link['href'] }}"
                        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-base-300 bg-base-100 p-3 text-center text-sm transition hover:border-primary/40 hover:bg-primary/5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                            </svg>
                        </span>
                        <span class="line-clamp-2">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
