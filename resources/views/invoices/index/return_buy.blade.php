<div class="card-actions flex items-center gap-3">
    @if ($service_buy)
        <a href="{{ route('invoices.create', ['invoice_type' => 'return_buy', 'service_buy' => '1']) }}"class="btn btn-primary">
            {{ __('Create return service buy invoice') }}
        </a>
    @else
        <a href="{{ route('invoices.create', ['invoice_type' => 'return_buy']) }}" class="btn btn-primary">{{ __('Create return buy invoice') }}</a>
    @endif
    @include('invoices.index.partials.search-form', [
        'invoiceType' => 'return_buy',
        'isSellWorkflow' => false,
        'showServiceBuy' => true,
        'showMoadian' => false,
        'showVoided' => false,
    ])
</div>

@include('invoices.index.partials.stat-cards', [
    'isSellWorkflow' => false,
    'quantityTitle' => $service_buy
        ? __('Returned Sold Services Quantity')
        : __('Returned Bought Products Quantity'),
    'quantityValue' => formatNumber(
        $service_buy ? $invoices->totalServicesQuantity : $invoices->totalProductsQuantity),
])

@include('invoices.index.partials.table', [
    'isSellWorkflow' => false,
    'isVoidWorkflow' => false,
    'showMoadianColumn' => false,
    'invoiceType' => 'return_buy',
])
