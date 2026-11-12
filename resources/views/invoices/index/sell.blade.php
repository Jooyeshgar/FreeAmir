<div class="card-actions flex flex-col items-stretch gap-3 lg:flex-row lg:items-start">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}" class="btn btn-primary">{{ __('Create sell invoice') }}</a>
        @can('invoices.sort-numbers')
            <form method="POST" action="{{ route('invoices.sort-numbers') }}" class="mb-4"
                onsubmit="return confirm(@js(__('Sort all invoice numbers in the active fiscal year? Existing printed invoice numbers will change.')))">
                @csrf
                <button class="btn btn-secondary" type="submit" @disabled(\App\Models\FiscalYear::find(getActiveFiscalYear())?->isClosed())>
                    {{ __('Sort invoice numbers') }}
                </button>
            </form>
        @endcan
    </div>
    @include('invoices.index.partials.search-form', [
        'invoiceType' => 'sell',
        'isSellWorkflow' => true,
        'showServiceBuy' => false,
        'showMoadian' => true,
        'showVoided' => true,
    ])
</div>

@include('invoices.index.partials.stat-cards', [
    'isSellWorkflow' => true,
    'quantityTitle' => __('Sold Products Quantity'),
    'quantityValue' => formatNumber($invoices->totalProductsQuantity),
])

@include('invoices.index.partials.table', [
    'isSellWorkflow' => true,
    'isVoidWorkflow' => false,
    'showMoadianColumn' => true,
    'invoiceType' => 'sell',
])