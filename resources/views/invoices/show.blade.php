<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        .none { border: none; }
        .border { border: 1px solid #000; }
        .right { text-align: right; direction: rtl; }
        .center { text-align: center !important; direction: rtl; }
        .pink { background-color: #ffe0eb; }
        body, table {
            font-family: "sahel";
            font-size: 15px;
            height: 100%;
        }
        .bold { font-weight: bold; font-size: 12px; }
        .lheight { line-height: 1.7em; text-align: right; }
        .vertical {
            writing-mode: vertical-rl;
            text-orientation: mixed;
        }
    </style>
</head>
<body>
    <table width="100%" cellspacing="2" cellpadding="8" style="table-layout:fixed;">
        <tbody>
            <tr>
                <td class="border right pink" width="" style="text-align:center;">{{ $invoice->taxID }}</td>
                <td rowspan="2" class="none" width="850" style="text-align:center;font-family:sahel-bold;font-weight:bold;font-size:20px;">
                    @if($invoice->invoice_type->value == 'buy')
                        صورتحساب خرید کالا و خدمات
                    @else
                        صورتحساب فروش کالا و خدمات
                    @endif
                </td>
            </tr>
            <tr>
                <td class="border right pink" width="">
                    شماره: <span style="color:#BB0000;">{{ convertToFarsi($invoice->number) }}</span>
                    &nbsp; - &nbsp;
                    تاریخ: {{ formatDate($invoice->date) }}
                </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="8" style="margin-top:2px;margin-left:2px;table-layout:fixed;" class="mytable">
        <tbody>
            <tr>
                <td colspan="4" class="border center pink">
                    @if($invoice->invoice_type->value == 'buy')
                        <p align="center" class="bold">مشخصات خریدار</p>
                    @else
                        <p align="center" class="bold">مشخصات فروشنده</p>
                    @endif
                </td>
            </tr>
            <tr class="right">
                <td class="right none lheight" width="33%" style="border-left:1px solid #000;">
                    شماره ملی: {{ convertToFarsi('10840096498') }}<br/>
                    شماره تلفن: <bdo dir="ltr">{{ convertToFarsi('031-32121091') }}</bdo>
                </td>
                <td class="right none lheight" width="33%">
                    شماره اقتصادی: {{ convertToFarsi('411337894159') }}<br/>
                    کد پستی ۱۰ رقمی: {{ convertToFarsi('8136613699') }}
                </td>
                <td class="right none lheight" width="27%">
                    شرکت مهندسی جویشگر پردیس ارم<br/>
                    دفتر مرکزی: اصفهان میدان امام حسین ارگ جهان نما فاز ۴ طبقه ۴ واحد ۱۶
                </td>
                <td class="right none" width="6%" style="border-right:1px solid #000;">
                    <img src="{{ public_path('img/Logo.svg') }}" width="90" height="80" align="left">
                </td>
            </tr>
            
            <tr>
                <td class="pink center border" colspan="4" width="100%">
                    @if($invoice->invoice_type->value == 'buy')
                        <p align="center" class="bold">مشخصات فروشنده</p>
                    @else
                        <p align="center" class="bold">مشخصات خریدار</p>
                    
                    @endif
                </td>
            </tr>
            <tr class="right">
                <td class="right none lheight" width="33%" style="border-left:1px solid #000;">
                    کد ملی: {{ isset($invoice->customer->personal_code) ? convertToFarsi($invoice->customer->personal_code) : '' }}<br/>
                    شماره تلفن: <bdo dir="ltr">{{ isset($invoice->customer->phone) ? convertToFarsi($invoice->customer->phone) : '' }}</bdo>
                </td>
                <td class="right none lheight" width="33%">
                    شماره اقتصادی: {{ isset($invoice->customer->ecnmcs_code) ? convertToFarsi($invoice->customer->ecnmcs_code) : '' }}<br/>
                    کد پستی ۱۰ رقمی: {{ isset($invoice->customer->postal_code) ? convertToFarsi($invoice->customer->postal_code) : '' }}
                </td>
                <td class="right none lheight" width="27%">
                    {{ $invoice->customer->name }}<br/>
                    {{ $invoice->customer->address }}
                </td>
                <td class="right none" width="6%" style="border-right:1px solid #000;">
                    <img src="{{ public_path('img/Empty.png') }}" width="90" height="80" align="left">
                </td>
            </tr>
        </tbody>
    </table>

    <table width="100%" cellspacing="0" cellpadding="8" style="text-align:right;table-layout:fixed;margin-left:2px;">
        <tbody>
            <tr valign="top">
                <td class="border center" style="border-right: none;" width="15%">
                    <p align="center" class="bold">جمع مبلغ کل</p>
                    <p align="center" class="bold">بعلاوه جمع مالیات و عوارض (ریال)</p>
                </td>
                <td class="border center" style="border-right: none;" width="8%">
                    <p align="center" class="bold">جمع مالیات و عوارض</p>
                    <p align="center" class="bold">(ریال)</p>
                </td>
                <td class="border center" style="border-right: none;" width="7%">
                    <p align="center" class="bold">مبلغ کل پس از تخفیف</p>
                    <p align="center" class="bold">(ریال)</p>
                </td>
                <td class="border center" style="border-right: none;" width="9%">
                    <p align="center" class="bold">مبلغ تخفیف</p>
                </td>
                <td class="border center" style="border-right: none;" width="11%">
                    <p align="center" class="bold">مبلغ کل</p>
                    <p align="center" class="bold">(ریال)</p>
                </td>
                <td class="border center" style="border-right: none;" width="11%">
                    <p align="center" class="bold">مبلغ واحد</p>
                    <p align="center" class="bold">(ریال)</p>
                </td>
                <td class="border center" style="border-right: none;" width="5%">
                    <p align="center" class="bold">واحد</p>
                    <p align="center" class="bold">اندازه گیری</p>
                </td>
                <td class="border center" style="border-right: none;" width="5%">
                    <p align="center" class="bold">تعداد</p>
                    <p align="center" class="bold">مقدار</p>
                </td>
                <td class="border center" style="border-right: none;" width="26%">
                    <p align="center" class="bold">شرح کالا یا خدمات</p>
                </td>
                <td class="border" style="border-right: none;" width="2%">
                    <p align="center" class="bold">کد</p>
                    <p align="center" class="bold">کالا</p>
                </td>
                <td class="border" width="1%">
                    <div style="position: relative">
                        <p class="vertical bold" style="position: absolute; right: 7mm; top: -3mm; rotate: -90; width: 100mm;font-size:10px;">ردیف</p>
                    </div>
                </td>
            </tr>
            @php
                $invoiceTotalPrice = 0;
                $invoiceTotalPriceAfterDiscount = 0;
                $invoiceTotalDiscount = 0;
            @endphp

            @foreach($invoiceItems as $index => $invoiceItem)
                @php
                    $itemQuantity = abs($invoiceItem->quantity);
                    $unitPrice = abs($invoiceItem->unit_price);
                    $totalPrice = $itemQuantity * $unitPrice;
                    $invoiceTotalPrice += $totalPrice;
                    $discountPrice = abs($invoiceItem->unit_discount) ?? 0;
                    $invoiceTotalDiscount += $discountPrice;
                    $totalPriceAfterDiscount = $totalPrice - $discountPrice;
                    $invoiceTotalPriceAfterDiscount += $totalPriceAfterDiscount;
                    $vatPrice = abs($invoiceItem->vat) ?? 0;
                    $total = ($totalPriceAfterDiscount + $vatPrice) ?? 0;

                @endphp
                <tr valign="top" style="text-align:center;">
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ formatNumber($total) }}</p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ formatNumber($vatPrice) }}</p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ formatNumber($totalPriceAfterDiscount) }}</p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ formatNumber($discountPrice) }}</p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ formatNumber($totalPrice) }}</p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ formatNumber($unitPrice) }}</p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right"> </p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ convertToFarsi((int) $itemQuantity) }}</p>
                    </td>
                    <td class="border center" style="border-right: none;">
                        <p align="right">{{ $invoiceItem->description }}</p>
                    </td>
                    <td class="border center" style="border border-right: none;">
                        <p align="right">{{ convertToFarsi($invoiceItem->product->code) ?? '' }}</p>
                    </td>
                    <td class="border" style="">
                        <p align="right" class="bold">{{ convertToFarsi($index + 1) }}</p>
                    </td>
                </tr>
            @endforeach

            <tr valign="top">
                <td class="border center" style="border-right: none;" width="9%">
                    <p align="right">{{ formatNumber($invoice->amount) }}</p>
                </td>
                <td class="border center" style="border-right: none;" width="15%">
                    <p align="right">{{ formatNumber((abs($invoice->vat))) }}</p>
                </td>
                <td class="border center" style="border-right: none;" width="13%">
                    <p align="right">{{ formatNumber($invoiceTotalPriceAfterDiscount) }}</p>
                </td>
                <td class="border center" style="border-right: none;" width="7%">
                    <p align="right">{{ formatNumber($invoiceTotalDiscount) }}</p>
                </td>
                <td class="border center" style="border-right: none;" width="7%">
                    <p align="right">{{ formatNumber($invoiceTotalPrice) }}</p>
                </td>
                <td colspan="6" class="border pink center" width="49%">
                    <p align="center">جمع کل (ریال): {{ formatNumber($invoice->amount) }}</p>
                </td>
            </tr>

            <tr valign="top">
                <td colspan="2" style="border: none;" width="24%">
                    <p align="right">مهر و امضاء خریدار</p>
                </td>
                <td colspan="3" style="border: none;" width="32%">
                    <p align="right">مهر و امضای فروشنده</p>
                </td>
                <td class="border" style="border-right: none;" width="9%">
                    <p align="right">غیر نقدی</p>
                    <input type="checkbox"/>
                </td>
                <td colspan="2" class="border" style="border-right: none;border-left:none;" width="17%">
                    <p align="right">نقدی</p>
                    <input type="checkbox" checked="checked"/>
                </td>
                <td colspan="3" class="border" width="18%" style="border-left:none;">
                    <p align="right">:شرایط و نحوه فروش</p>
                </td>
            </tr>

            <tr valign="top">
                <td colspan="5" style="border: none;" width="56%">
                    <p align="right"><br></p>
                </td>
                <td colspan="6" class="border" width="44%">
                    @if(strlen($invoice->description) < 3)
                        توضیحات:
                    @else
                        توضیحات: <span>{{ $invoice->description }}</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>