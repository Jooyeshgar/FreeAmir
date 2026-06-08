<div class="card-actions flex items-center gap-3">
    @if ($service_buy)
        <a href="{{ route('invoices.create', ['invoice_type' => 'buy', 'service_buy' => '1']) }}" class="btn btn-primary">{{ __('Service Buy Invoice') }}</a>
    @else
        <a href="{{ route('invoices.create', ['invoice_type' => 'buy']) }}" class="btn btn-primary">{{ __('Create buy invoice') }}</a>
    @endif
    @include('invoices.index.partials.search-form', [
        'invoiceType' => 'buy',
        'isSellWorkflow' => false,
        'showServiceBuy' => true,
        'showMoadian' => false,
        'showVoided' => false,
    ])
</div>

@include('invoices.index.partials.stat-cards', [
    'isSellWorkflow' => false,
    'quantityTitle' => $service_buy ? __('Bought Services Quantity') : __('Bought Products Quantity'),
    'quantityValue' => formatNumber(
        $service_buy ? $invoices->totalServicesQuantity : $invoices->totalProductsQuantity),
])

@include('invoices.index.partials.table', [
    'isSellWorkflow' => false,
    'isVoidWorkflow' => false,
    'showMoadianColumn' => false,
    'invoiceType' => 'buy',
])