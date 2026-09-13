<div class="card-actions flex flex-col items-stretch gap-3 lg:flex-row lg:items-start">
    <div>
        <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}" class="btn btn-primary">{{ __('Create sell invoice') }}</a>
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