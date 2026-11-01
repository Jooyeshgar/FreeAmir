<div class="card-actions flex flex-col items-stretch gap-3 lg:flex-row lg:items-start">
    <div class="flex flex-wrap gap-2">
        @if ($service_buy)
            <a href="{{ route('invoices.create', ['invoice_type' => 'return_buy', 'service_buy' => '1']) }}" class="btn btn-primary">
                {{ __('Create return service buy invoice') }}
            </a>
        @else
            <a href="{{ route('invoices.create', ['invoice_type' => 'return_buy']) }}" class="btn btn-primary">{{ __('Create return buy invoice') }}</a>
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
        ? __('Returned Bought Services Quantity')
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
