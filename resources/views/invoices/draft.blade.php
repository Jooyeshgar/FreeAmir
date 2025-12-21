<x-report-layout>
    @vite('resources/css/invoice_draft.css')

    <div class="invoice-container">
        <div class="invoice-top-left">
            <span>{{ __('Invoice Number') }}: {{ convertToFarsi(intval($invoice->number ?? 0)) ?? '' }}</span>
            <span>{{ __('Date') }}: {{ formatDate($invoice->created_at) }}</span>
        </div>

        <div class="invoice-header">
            {{ __('Pre Invoice') }} {{ $invoice->invoice_type->label() ?? '' }}
        </div>

        <div class="invoice-info">
            <div class="info-column">
                <div class="info-row">
                    <span>{{ __('Name') }} {{ __('Customer') }}:</span>
                    <span>{{ $invoice->customer->name }}</span>
                </div>
                <div class="info-row">
                    <span>{{ __('Address') }}:</span>
                    <span class="smaller-text">{{ $invoice->customer->address }}</span>
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
                    <th>{{ __('Total Price') }}</th>
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
                @for (; $index < 6; $index++)
                    <tr>
                        <td>{{ convertToFarsi($index) }}</td>
                        <td> </td>
                        <td> </td>
                        <td> </td>
                        <td> </td>
                        <td> </td>
                    </tr>
                @endfor
                <tr>
                    <td colspan="5" style="text-align: right;">{{ __('Total Sum') }}:
                        {{ App\Helpers\NumberToWordHelper::convert($totalAmount) }}
                        {{ config('amir.currency') ?? __('Rial') }}
                    </td>
                    <td>{{ formatNumber($totalAmount) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-report-layout>
