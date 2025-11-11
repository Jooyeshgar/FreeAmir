<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" href="{{ resource_path('css/invoice.css') }}">
</head>

<body>
    <table width="100%" cellspacing="2" cellpadding="4" style="table-layout:fixed;">
        <tbody>
            <tr>
                <td class="border right pink">شماره: <span style="color:#BB0000;">{{ formatDocumentNumber($invoice->number) }}</span></td>
                <td rowspan="2" class="none" style="text-align:center;font-weight:bold;font-size:20px; width:80%;">
                    @if ($invoice->invoice_type->value == 'buy')
                        صورتحساب خرید کالا و خدمات
                    @elseif ($invoice->invoice_type->value == 'sell')
                        صورتحساب فروش کالا و خدمات
                    @elseif ($invoice->invoice_type->value == 'return_buy')
                        صورتحساب برگشت از خرید
                    @elseif ($invoice->invoice_type->value == 'return_sell')
                        صورتحساب برگشت از فروش
                    @endif
                </td>
            </tr>
            <tr>
                <td class="border right pink">
                    <span> تاریخ: {{ formatDate($invoice->date) }}</span>
                </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="4" style="margin-top:2px;margin-left:2px; align-item:right;">
        <tbody>
            <tr>
                <td colspan="4" class="border center pink mainlineheight">
                    @if ($invoice->invoice_type->value == 'buy' || $invoice->invoice_type->value == 'return_buy')
                        <p align="center" class="bold">مشخصات خریدار</p>
                    @else
                        <p align="center" class="bold">مشخصات فروشنده</p>
                    @endif
                </td>
            </tr>
            <tr class="right" width="100%">
                <td class="right none lheight" width="33%" style="border-left:1px solid #000;">
                    شماره ملی: {{ convertToFarsi('10840096498') }}<br />
                    شماره تلفن: <bdo dir="ltr">{{ convertToFarsi('031-32121091') }}</bdo>
                </td>
                <td class="right none lheight" width="33%">
                    شماره اقتصادی: {{ convertToFarsi('411337894159') }}<br />
                    کد پستی ۱۰ رقمی: {{ convertToFarsi('8136613699') }}
                </td>
                <td class="right none lheight" width="27%">
                    شرکت مهندسی جویشگر پردیس ارم<br />
                    دفتر مرکزی: اصفهان میدان امام حسین ارگ جهان نما فاز ۴ طبقه ۴ واحد ۱۶
                </td>
                <td class="right none" width="6%" style="border-right:1px solid #000;">
                    @php $logo = base64_encode(file_get_contents(public_path('images/logo.png'))); @endphp
                    <img src="data:image/png;base64,{{ $logo }}" width="90" height="80" align="left">
                </td>
            </tr>

            <tr>
                <td class="pink center border mainlineheight" colspan="4" width="100%">
                    @if ($invoice->invoice_type->value == 'buy' || $invoice->invoice_type->value == 'return_buy')
                        <p align="center" class="bold">مشخصات فروشنده</p>
                    @else
                        <p align="center" class="bold">مشخصات خریدار</p>
                    @endif
                </td>
            </tr>
            <tr class="right">
                <td class="right none lheight" width="33%" style="border-left:1px solid #000;">
                    شماره ملی: {{ isset($invoice->customer->personal_code) ? convertToFarsi($invoice->customer->personal_code) : '' }}<br />
                    شماره تلفن: <bdo dir="ltr">{{ isset($invoice->customer->phone) ? convertToFarsi($invoice->customer->phone) : '' }}</bdo>
                </td>
                <td class="right none lheight" width="33%">
                    شماره اقتصادی: {{ isset($invoice->customer->ecnmcs_code) ? convertToFarsi($invoice->customer->ecnmcs_code) : '' }}<br />
                    کد پستی ۱۰ رقمی: {{ isset($invoice->customer->postal_code) ? convertToFarsi($invoice->customer->postal_code) : '' }}
                </td>
                <td class="right none lheight" width="33%">
                    {{ $invoice->customer->name }}<br />
                    {{ $invoice->customer->address }}
                </td>
                <td class="right none" width="6%" style="border-right:1px solid #000;">
                    @php $user = base64_encode(file_get_contents(public_path('images/user.jpg'))); @endphp
                    <img src="data:image/jpeg;base64,{{ $user }}" width="90" height="80" align="left">
                </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" style="text-align:right;table-layout:fixed;margin-left:2px;">
        <tbody>
            <tr valign="top" style="line-height:1em;">
                <td class="border" style="border-right: none;" width="15%">
                    <p align="center" class="bold">جمع مبلغ کل
                        بعلاوه جمع مالیات و عوارض
                        (ریال)
                    </p>
                </td>
                <td class="border" style="border-right: none;" width="12%">
                    <p align="center" class="bold">جمع مالیات و عوارض (ریال)</p>
                </td>
                <td class="border" style="border-right: none;" width="10%">
                    <p align="center" class="bold">مبلغ کل پس از تخفیف (ریال)
                    </p>
                </td>
                <td class="border" style="border-right: none;" width="4%">
                    <p align="center" class="bold">مبلغ تخفیف</p>
                </td>
                <td class="border" style="border-right: none;" width="5%">
                    <p align="center" class="bold">مبلغ کل (ریال)</p>
                </td>
                <td class="border" style="border-right: none;" width="5%">
                    <p align="center" class="bold">مبلغ واحد (ریال)</p>
                </td>
                <td class="border" style="border-right: none;" width="4%">
                    <p align="center" class="bold">واحد اندازه گیری</p>
                </td>
                <td class="border" style="border-right: none;" width="4%">
                    <p align="center" class="bold">تعداد مقدار</p>
                </td>
                <td class="border" style="border-right: none;" width="20%">
                    <p align="center" class="bold">شرح کالا یا خدمات</p>
                </td>
                <td class="border" style="border-right: none;" width="2%">
                    <p align="center" class="bold">کد کالا</p>
                </td>
                <td class="border" width="2%">
                    <p class="vertical bold" align="center" style="font-size:8pt;width:50%;margin-top:10pt">ردیف</p>
                </td>
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
                <tr valign="top">
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ formatNumber($total) }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ formatNumber($vatPrice) }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ formatNumber($totalPriceAfterDiscount) }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ formatNumber($discountPrice) }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ formatNumber($totalPrice) }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ formatNumber($unitPrice) }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center"> </p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ convertToFarsi((int) $itemQuantity) }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border-right: none;">
                        <p align="center">{{ $invoiceItem->description }}</p>
                    </td>
                    <td class="border center mainlineheight" style="border border-right: none;">
                        <p align="center">{{ formatCode($code) }}</p>
                    </td>
                    <td class="border center mainlineheight">
                        <p align="center" class="bold">{{ convertToFarsi($index + 1) }}</p>
                    </td>
                </tr>
            @endforeach

            <tr valign="top">
                <td class="border center mainlineheight" style="border-right: none;" width="9%">
                    <p align="center">{{ formatNumber($invoice->amount) }}</p>
                </td>
                <td class="border center mainlineheight" style="border-right: none;" width="15%">
                    <p align="center">{{ formatNumber(abs($invoice->vat)) }}</p>
                </td>
                <td class="border center mainlineheight" style="border-right: none;" width="13%">
                    <p align="center">{{ formatNumber($invoiceTotalPriceAfterDiscount) }}</p>
                </td>
                <td class="border center mainlineheight" style="border-right: none;" width="7%">
                    <p align="center">{{ formatNumber($invoiceTotalDiscount) }}</p>
                </td>
                <td class="border center mainlineheight" style="border-right: none;" width="7%">
                    <p align="center">{{ formatNumber($invoiceTotalPrice) }}</p>
                </td>
                <td colspan="6" class="border pink center mainlineheight" width="45%">
                    <p align="center">جمع کل (ریال): {{ formatNumber($invoice->amount) }}</p>
                </td>
            </tr>

            <tr valign="top">
                <td colspan="2" class="mainlineheight" style="border: none;" width="24%">
                    <p align="right">مهر و امضاء خریدار</p>
                </td>
                <td colspan="3" class="mainlineheight" style="border: none;" width="32%">
                    <p align="right">مهر و امضای فروشنده</p>
                </td>

                <td colspan="2" class="border" style="border-right: none;" width="9%">
                    <label style="display: flex; align-items: center; justify-content: center;">
                        <span style="">غیر نقدی</span>
                        <input type="checkbox" />
                    </label>
                </td>

                <td class="border" style="border-left: none; border-right: none;" width="9%">
                    <label style="display: flex; align-items: center; justify-content: center;">
                        <span style="">نقدی</span>
                        <input type="checkbox" checked />
                    </label>
                </td>

                <td colspan="3" class="border mainlineheight" style="border-left: none;" width="24%">
                    <p align="right">:شرایط و نحوه فروش</p>
                </td>
            </tr>

            <tr valign="top">
                <td colspan="5" class="mainlineheight" style="border: none;" width="56%">
                    <p align="right"><br></p>
                </td>
                <td colspan="6" class="border mainlineheight" width="44%">
                    <p align="right">
                        @if (strlen($invoice->description) < 3)
                            :توضیحات
                        @else
                            توضیحات: {{ $invoice->description }}
                        @endif
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
