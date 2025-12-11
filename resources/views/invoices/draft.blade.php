<style>
    body {
        font-family: 'Arial', sans-serif;
        direction: rtl;
        margin: 0;
    }

    .invoice-container {
        border: 1px solid #000;
        padding: 30px;
        margin: 20px auto;
        max-width: 900px;
        position: relative;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
    }

    .invoice-top-left {
        position: absolute;
        top: 20px;
        left: 20px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .invoice-top-left span {
        display: block;
    }

    .invoice-header {
        text-align: center;
        font-weight: bold;
        padding: 15px 0;
        margin-bottom: 20px;
        font-size: 20px;
        border-radius: 5px;
    }

    .invoice-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 25px;
        padding: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    .info-column {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
    }

    .info-row {
        display: flex;
        font-size: 15px;
    }

    .invoice-items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: 14px;
        direction: rtl;
    }

    .invoice-items-table th,
    .invoice-items-table td {
        border: 1px solid #000;
        padding: 8px 12px;
        text-align: center;
        word-break: break-word;
    }

    .invoice-totals {
        display: flex;
        justify-content: flex-end;
        font-size: 16px;
        margin-top: 15px;
    }

    .invoice-totals span {
        margin-left: 10px;
        font-weight: bold;
    }

    @media print {
        @page {
            size: A4;
            margin: 5mm 5mm 0 5mm;

        }

        body {
            background-color: #fff;
        }

        .invoice-container {
            box-shadow: none;
            padding: 20px;
            margin: 0;
        }

        .invoice-items-table {
            font-size: 12px;
        }
    }
</style>

<div class="invoice-container">

    <div class="invoice-top-left">
        <span>{{ __('Number') }}: {{ formatDocumentNumber($invoice->number ?? 0) ?? '' }}</span>
        <span>{{ __('Date') }}: {{ formatDate($invoice->created_at) }}</span>
    </div>

    <div class="invoice-header">
        {{ __('Draft') }} {{ __('Invoice') }} {{ $invoice->invoice_type->label() ?? '' }}
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
                    if ($item->itemable_type != 'App\Models\Product') {
                        continue;
                    }
                    $product = App\Models\Product::find($item->itemable_id);
                    $totalAmount += $item->amount - $item->vat;
                @endphp
                <tr>
                    <td>{{ $index++ }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ convertToFarsi($item->quantity) }}</td>
                    <td>{{ convertToFarsi($item->unit_price) }}</td>
                    <td>{{ convertToFarsi($item->unit_discount) }}</td>
                    <td>{{ convertToFarsi($item->amount - $item->vat) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="invoice-totals">
        <span>{{ __('Total Sum') }}: {{ convertToFarsi($totalAmount) }}</span>
    </div>

</div>
