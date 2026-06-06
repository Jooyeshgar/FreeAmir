<div class="card-actions flex items-center gap-3">
    <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}" class="btn btn-primary">{{ __('Create sell invoice') }}</a>
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