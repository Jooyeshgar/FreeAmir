<div class="card-actions flex flex-col items-stretch gap-3 lg:flex-row lg:items-start">
    <div class="flex flex-wrap gap-2">
        @if ($service_buy)
            <a href="{{ route('invoices.create', ['invoice_type' => 'buy', 'service_buy' => '1']) }}" class="btn btn-primary">{{ __('Service Buy Invoice') }}</a>
        @else
            <a href="{{ route('invoices.create', ['invoice_type' => 'buy']) }}" class="btn btn-primary">{{ __('Create buy invoice') }}</a>
        @endif
        @can('invoices.sort-numbers')
            <form method="POST" action="{{ route('invoices.sort-numbers') }}"
                onsubmit="return confirm(@js(__('Sort all invoice numbers in the active fiscal year? Existing printed invoice numbers will change.')))">
                @csrf
                <button class="btn btn-secondary" type="submit" @disabled(\App\Models\FiscalYear::find(getActiveFiscalYear())?->isClosed())>
                    {{ __('Sort invoice numbers') }}
                </button>
            </form>
        @endcan
    </div>
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