<div class="card-actions flex flex-col items-stretch gap-3 lg:flex-row lg:items-start">
    @include('invoices.index.partials.search-form', [
        'invoiceType' => 'void',
        'isSellWorkflow' => false,
        'showServiceBuy' => false,
        'showMoadian' => true,
        'showVoided' => false,
    ])
</div>

@include('invoices.index.partials.stat-cards', [
    'isSellWorkflow' => false,
    'quantityTitle' => __('Voided Sold Products Quantity'),
    'quantityValue' => formatNumber($invoices->totalProductsQuantity),
])

@include('invoices.index.partials.table', [
    'isSellWorkflow' => false,
    'isVoidWorkflow' => true,
    'showMoadianColumn' => true,
    'invoiceType' => 'void',
])
