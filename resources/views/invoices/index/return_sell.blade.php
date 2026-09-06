
<div class="card-actions flex flex-col items-stretch gap-3 lg:flex-row lg:items-start">
    <div>
        <a href="{{ route('invoices.create', ['invoice_type' => 'return_sell']) }}" class="btn btn-primary">{{ __('Create return sell invoice') }}</a>
    </div>
    @include('invoices.index.partials.search-form', [
        'invoiceType' => 'return_sell',
        'isSellWorkflow' => false,
        'showServiceBuy' => false,
        'showMoadian' => true,
        'showVoided' => false,
    ])
</div>

@include('invoices.index.partials.stat-cards', [
    'isSellWorkflow' => false,
    'quantityTitle' => __('Returned Sold Products Quantity'),
    'quantityValue' => formatNumber($invoices->totalProductsQuantity),
])

@include('invoices.index.partials.table', [
    'isSellWorkflow' => false,
    'isVoidWorkflow' => false,
    'showMoadianColumn' => true,
    'invoiceType' => 'return_sell',
])
