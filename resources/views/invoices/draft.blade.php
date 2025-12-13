<style>
    {!! file_get_contents(resource_path('css/invoice_draft.css')) !!}
</style>

<div class="invoice-container">
    <div class="invoice-top-left">
        <span>{{ __('Invoice Number') }}: {{ formatDocumentNumber($invoice->number ?? 0) ?? '' }}</span>
        <span>{{ __('Date') }}: {{ formatDate($invoice->created_at) }}</span>
    </div>

    <div class="invoice-header">
        {{ __('Pre-Invoice') }} {{ $invoice->invoice_type->label() ?? '' }}
    </div>

    <div class="invoice-info">
        <div class="info-column">
            <div class="info-row">
                <span>{{ __('Name') }} {{ __('Customer') }}:</span>
                <span>{{ $invoice->customer->name }}</span>
            </div>
            <div class="info-row">
                <span>{{ __('Address') }}:</span>
                <span>{{ $invoice->customer->address }}</span>
            </div>
        </div>
        <div class="info-column">
            <div class="info-row">
                <span>{{ __('Phone') }}:</span>
                <span>{{ convertToFarsi($invoice->customer->phone) }}</span>
            </div>
            <div class="info-row">
                <span>{{ __('Postal Code') }}:</span>
                <span>{{ convertToFarsi($invoice->customer->postal_code) }}</span>
            </div>
        </div>
    </div>

    <table class="invoice-items-table">
        <thead>
            <tr>
                <th>{{ __('Index') }}</th>
                <th>{{ __('Product Name') }}</th>
                <th>{{ __('Quantity') }}</th>
                <th>{{ __('Unit Price') }}</th>
                <th>{{ __('OFF') }}</th>
                <th>{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php
                $index = 1;
                $totalAmount = 0;
            @endphp
            @foreach ($invoice->items as $item)
                @php
                    $lineTotal = $item->amount - $item->vat;
                    $totalAmount += $lineTotal;
                @endphp
                <tr>
                    <td>{{ convertToFarsi($index++) }}</td>
                    <td>{{ $item->itemable->name }}</td>
                    <td>{{ formatNumber($item->quantity) }}</td>
                    <td>{{ formatNumber($item->unit_price) }}</td>
                    <td>{{ formatNumber($item->unit_discount) }}</td>
                    <td>{{ formatNumber($lineTotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="invoice-totals">
        <span>{{ __('Total Sum') }}: {{ formatNumber($totalAmount) }}</span>
    </div>

</div>
