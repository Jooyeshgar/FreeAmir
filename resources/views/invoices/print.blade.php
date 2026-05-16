<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" href="{{ resource_path('css/invoice.css') }}">
</head>

<body>
    <table width="100%">
        <tbody>
            <tr>
                <td class="invoiceInfo"></td>
                <td class="invoiceType" rowspan="2">
                    @if ($invoice->invoice_type == App\Enums\InvoiceType::BUY)
                        صورتحساب خرید کالا و خدمات
                    @elseif ($invoice->invoice_type == App\Enums\InvoiceType::SELL)
                        صورتحساب فروش کالا و خدمات
                    @elseif ($invoice->invoice_type == App\Enums\InvoiceType::RETURN_BUY)
                        صورتحساب برگشت از خرید
                    @elseif ($invoice->invoice_type == App\Enums\InvoiceType::RETURN_SELL)
                        صورتحساب برگشت از فروش
                    @elseif ($invoice->invoice_type == App\Enums\InvoiceType::VOID)
                        صورتحساب ابطال فروش
                    @endif
                </td>
            </tr>
            <tr>
                <td class="invoiceInfo">
                    شماره: <span class="red">{{ formatDocumentNumber($invoice->number) }}</span> -
                    <span> تاریخ: {{ formatDate($invoice->date) }}</span>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="contractTable" cellspacing="0" cellpadding="4">
        <tbody>
            <tr>
                <td class="contractTitle" colspan="4">
                    @if ($invoice->invoice_type == App\Enums\InvoiceType::BUY || $invoice->invoice_type == App\Enums\InvoiceType::RETURN_BUY)
                        مشخصات خریدار
                    @else
                        مشخصات فروشنده
                    @endif
                </td>
            </tr>
            <tr>
                <td class="contractSection" width="33%">
                    شماره ملی: {{ convertToFarsi('10840096498') }}<br />
                    شماره تلفن: {{ convertToFarsi('031') }}<bdo dir="ltr">-</bdo>{{ convertToFarsi('32121091') }}
                </td>
                <td class="contractSection" width="30%">
                    شماره اقتصادی: {{ convertToFarsi('411337894159') }}<br />
                    کد پستی ۱۰ رقمی: {{ convertToFarsi('8136613699') }}
                </td>
                <td class="contractSection" width="30%">
                    شرکت مهندسی جویشگر پردیس ارم<br />
                    دفتر مرکزی: اصفهان میدان امام حسین ارگ جهان نما فاز ۴ طبقه ۴ واحد ۱۶
                </td>
                <td class="logo">
                    @php $logo = base64_encode(file_get_contents(public_path('images/logo.svg'))); @endphp
                    <img src="data:image/png;base64,{{ $logo }}" width="70" height="70" align="left">
                </td>
            </tr>

            <tr>
                <td class="contractTitle" colspan="4">
                    @if ($invoice->invoice_type == App\Enums\InvoiceType::BUY || $invoice->invoice_type == App\Enums\InvoiceType::RETURN_BUY)
                        مشخصات فروشنده
                    @else
                        مشخصات خریدار
                    @endif
                </td>
            </tr>
            <tr>
                <td class="contractSection">
                    شماره ملی:
                    {{ isset($invoice->customer->personal_code) ? convertToFarsi($invoice->customer->personal_code) : '' }}<br />
                    شماره تلفن: <bdo
                        dir="ltr">{{ isset($invoice->customer->phone) ? convertToFarsi($invoice->customer->phone) : '' }}</bdo>
                </td>
                <td class="contractSection">
                    شماره اقتصادی:
                    {{ isset($invoice->customer->ecnmcs_code) ? convertToFarsi($invoice->customer->ecnmcs_code) : '' }}<br />
                    کد پستی ۱۰ رقمی:
                    {{ isset($invoice->customer->postal_code) ? convertToFarsi($invoice->customer->postal_code) : '' }}
                </td>
                <td class="contractSection">
                    {{ $invoice->customer->name }}<br />
                    {{ $invoice->customer->address }}
                </td>
                <td class="contractSection"></td>
            </tr>
        </tbody>
    </table>

    <table class="transactionsTable" cellspacing="0">
        <tbody>
            <tr>
                <td class="transactionsHeader" width="12%">جمع مبلغ کل بعلاوه جمع مالیات و عوارض (ریال)</td>
                <td class="transactionsHeader" width="12%">جمع مالیات و عوارض (ریال)</td>
                <td class="transactionsHeader" width="12%">مبلغ کل پس از تخفیف (ریال)</td>
                <td class="transactionsHeader" width="9%">مبلغ تخفیف</td>
                <td class="transactionsHeader" width="9%">مبلغ کل (ریال)</td>
                <td class="transactionsHeader" width="9%">مبلغ واحد (ریال)</td>
                <td class="transactionsHeader" width="4%">واحد اندازه گیری</td>
                <td class="transactionsHeader" width="5%">تعداد مقدار</td>
                <td class="transactionsHeader" width="21%">شرح کالا یا خدمات</td>
                <td class="transactionsHeader" width="6%">کد کالا</td>
                <td class="transactionsHeaderIndex" width="2.5%">ردیف</td>
            </tr>
            @php
                $invoiceTotalPrice = 0;
                $invoiceTotalPriceAfterDiscount = 0;
                $invoiceTotalDiscount = 0;
            @endphp

            @foreach ($invoice->items as $index => $invoiceItem)
                @php
                    $itemQuantity = $invoiceItem->quantity;
                    $unitPrice = $invoiceItem->unit_price;
                    $totalPrice = $itemQuantity * $unitPrice;
                    $invoiceTotalPrice += $totalPrice;
                    $discountPrice = $invoiceItem->unit_discount ?? 0;
                    $invoiceTotalDiscount += $discountPrice;
                    $totalPriceAfterDiscount = $totalPrice - $discountPrice;
                    $invoiceTotalPriceAfterDiscount += $totalPriceAfterDiscount;
                    $vatPrice = $invoiceItem->vat ?? 0;
                    $total = $totalPriceAfterDiscount + $vatPrice ?? 0;
                    $code = $invoiceItem->itemable->code ?? '';
                @endphp
                <tr>
                    <td class="transactionsRow">{{ formatNumber($total) }}</td>
                    <td class="transactionsRow">{{ formatNumber($vatPrice) }}</td>
                    <td class="transactionsRow">{{ formatNumber($totalPriceAfterDiscount) }}</td>
                    <td class="transactionsRow">{{ formatNumber($discountPrice) }}</td>
                    <td class="transactionsRow">{{ formatNumber($totalPrice) }}</td>
                    <td class="transactionsRow">{{ formatNumber($unitPrice) }}</td>
                    <td class="transactionsRow"></td>
                    <td class="transactionsRow">{{ formatNumber((int) $itemQuantity) }}</td>
                    <td class="transactionsRow">
                        {{ $invoiceItem->description ?? ($invoiceItem->itemable?->name ?? '') }}
                    </td>
                    <td class="transactionsRow">{{ convertToFarsi($code) }}</td>
                    <td class="transactionsRow transactionsRowIndex">{{ convertToFarsi($index + 1) }}</td>
                </tr>
            @endforeach

            <tr>
                <td class="transactionsRow">{{ formatNumber($invoice->amount) }}</td>
                <td class="transactionsRow">{{ formatNumber(abs($invoice->vat)) }}</td>
                <td class="transactionsRow">{{ formatNumber($invoiceTotalPriceAfterDiscount) }}</td>
                <td class="transactionsRow">{{ formatNumber($invoiceTotalDiscount) }}</td>
                <td class="transactionsRow">{{ formatNumber($invoiceTotalPrice) }}</td>
                <td colspan="6" class="totalInvoiceTransactions">جمع کل (ریال): {{ formatNumber($invoice->amount) }}
                </td>
            </tr>

            <tr>
                <td colspan="2" class="signature">مهر و امضاء خریدار</td>
                <td colspan="3" class="signature">مهر و امضای فروشنده</td>
                <td colspan="2" class="cashType">
                    <span>غیر نقدی</span>
                    <input type="checkbox" />
                </td>

                <td class="cashType leftBorder">
                    <span>نقدی</span>
                    <input type="checkbox" />
                </td>

                <td colspan="3" class="payType">:شرایط و نحوه فروش</td>
            </tr>

            <tr>
                <td colspan="5"></td>
                <td colspan="6" class="invoiceDesc">
                    @if (strlen($invoice->description) < 3)
                        :توضیحات
                    @else
                        توضیحات: {{ $invoice->description }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
