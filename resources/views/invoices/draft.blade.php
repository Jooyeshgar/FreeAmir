<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_type->isBeginningInventory() ? __('Beginning Inventory') : __('Pre Invoice') }}</title>
    @vite('resources/css/invoice-draft.css')
</head>
<body>
    <div class="draft-invoice">
        <div class="invoice-meta">
            <div>{{ __('Invoice Number') }}: {{ localizeNumber(intval($invoice->number ?? 0)) ?? '' }}</div>
            <div>{{ __('Date') }}: {{ formatDate($invoice->created_at) }}</div>
        </div>

        <div class="invoice-title">
            {{ $invoice->invoice_type->isBeginningInventory() ? __('Beginning Inventory') : __('Pre Invoice').' '.$invoice->invoice_type->label() }}
        </div>

        <table class="customer-details"><tr>
            <td class="customer-column">
                <div>
                    <span>{{ __('Name') }} {{ __('Customer') }}:</span>
                    <span>{{ $invoice->customer?->name ?? __('Beginning Inventory') }}</span>
                </div>
                <div>
                    <span>{{ __('Address') }}:</span>
                    <span class="address">{{ $invoice->customer?->address }}</span>
                </div>
            </td>
            <td class="customer-column">
                <div>
                    @if ($invoice->customer?->mobile)
                        <span>{{ __('Mobile') }}:</span>
                        <span>{{ localizeNumber($invoice->customer?->mobile) }}</span>
                    @elseif ($invoice->customer?->phone)
                        <span>{{ __('Phone') }}:</span>
                        <span>{{ localizeNumber($invoice->customer?->phone) }}</span>
                    @else
                        <span>{{ __('Phone') }}:</span>
                        <span>-</span>
                    @endif
                </div>
                <div>
                    <span>{{ __('Postal Code') }}:</span>
                    <span>{{ localizeNumber($invoice->customer?->postal_code) }}</span>
                </div>
            </td>
        </tr></table>

        <table class="invoice-items">
            <thead>
                <tr>
                    <th class="index-column">{{ __('Index') }}</th>
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
                        <td>{{ localizeNumber($index++) }}</td>
                        <td class="item-name">{{ $item->itemable?->name ?? __('Removed product/service') }}</td>
                        <td>{{ formatNumber($item->quantity) }}</td>
                        <td>{{ formatNumber($item->unit_price) }}</td>
                        <td>{{ formatNumber($item->unit_discount) }}</td>
                        <td>{{ formatNumber($lineTotal) }}</td>
                    </tr>
                @endforeach
                @for (; $index < 6; $index++)
                    <tr class="empty-row">
                        <td>{{ localizeNumber($index) }}</td>
                        <td> </td>
                        <td> </td>
                        <td> </td>
                        <td> </td>
                        <td> </td>
                    </tr>
                @endfor
                <tr class="total-row">
                    <td class="total-label" colspan="5">{{ __('Total Sum') }}:
                        {{ App\Helpers\NumberToWordHelper::convert($totalAmount) }}
                        {{ config('amir.currency') ?? __('Rial') }}
                    </td>
                    <td>{{ formatNumber($totalAmount) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
<script>
    window.addEventListener('load', async () => {
        await document.fonts.ready;
        window.print();
    });
</script>