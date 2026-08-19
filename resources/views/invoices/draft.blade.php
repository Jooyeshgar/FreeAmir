<x-report-layout title="{{ __('Pre Invoice') }}">
    <div class="relative box-border w-full border-4 border-double border-black bg-white p-8 shadow-sm print:m-0 print:min-h-[279mm] print:p-7 print:shadow-none">
        <div class="absolute left-7 top-7 flex flex-col gap-1.5 text-sm print:text-xs">
            <span>{{ __('Invoice Number') }}: {{ localizeNumber(intval($invoice->number ?? 0)) ?? '' }}</span>
            <span>{{ __('Date') }}: {{ formatDate($invoice->created_at) }}</span>
        </div>

        <div class="mb-5 py-14 text-center text-xl font-bold print:mb-4 print:pb-8 print:pt-12">
            {{ __('Pre Invoice') }} {{ $invoice->invoice_type->label() ?? '' }}
        </div>

        <div class="mb-6 grid grid-cols-2 gap-6 border border-black p-4 text-sm print:mb-4 print:p-3 print:text-xs">
            <div class="flex flex-col gap-2">
                <div>
                    <span>{{ __('Name') }} {{ __('Customer') }}:</span>
                    <span>{{ $invoice->customer->name }}</span>
                </div>
                <div>
                    <span>{{ __('Address') }}:</span>
                    <span class="text-xs">{{ $invoice->customer->address }}</span>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <div>
                    @if ($invoice->customer->mobile)
                        <span>{{ __('Mobile') }}:</span>
                        <span>{{ localizeNumber($invoice->customer->mobile) }}</span>
                    @elseif ($invoice->customer->phone)
                        <span>{{ __('Phone') }}:</span>
                        <span>{{ localizeNumber($invoice->customer->postal_code) }}</span>
                    @else
                        <span>{{ __('Phone') }}:</span>
                        <span>-</span>
                    @endif
                </div>
                <div>
                    <span>{{ __('Postal Code') }}:</span>
                    <span>{{ localizeNumber($invoice->customer->postal_code) }}</span>
                </div>
            </div>
        </div>

        <table class="table table-xs w-full table-fixed border-collapse text-center print:text-[9pt]">
            <thead>
                <tr>
                    <th class="w-12 border border-black bg-gray-100 text-center font-normal text-black">{{ __('Index') }}</th>
                    <th class="border border-black bg-gray-100 text-center font-normal text-black">{{ __('Product Name') }}</th>
                    <th class="border border-black bg-gray-100 text-center font-normal text-black">{{ __('Quantity') }}</th>
                    <th class="border border-black bg-gray-100 text-center font-normal text-black">{{ __('Unit Price') }}</th>
                    <th class="border border-black bg-gray-100 text-center font-normal text-black">{{ __('OFF') }}</th>
                    <th class="border border-black bg-gray-100 text-center font-normal text-black">{{ __('Total Price') }}</th>
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
                    <tr class="break-inside-avoid">
                        <td class="border border-black text-center">{{ localizeNumber($index++) }}</td>
                        <td class="whitespace-normal break-words border border-black text-center">{{ $item->itemable->name }}</td>
                        <td class="border border-black text-center">{{ formatNumber($item->quantity) }}</td>
                        <td class="border border-black text-center">{{ formatNumber($item->unit_price) }}</td>
                        <td class="border border-black text-center">{{ formatNumber($item->unit_discount) }}</td>
                        <td class="border border-black text-center">{{ formatNumber($lineTotal) }}</td>
                    </tr>
                @endforeach
                @for (; $index < 6; $index++)
                    <tr class="h-9 break-inside-avoid">
                        <td class="border border-black text-center">{{ localizeNumber($index) }}</td>
                        <td class="border border-black"> </td>
                        <td class="border border-black"> </td>
                        <td class="border border-black"> </td>
                        <td class="border border-black"> </td>
                        <td class="border border-black"> </td>
                    </tr>
                @endfor
                <tr class="break-inside-avoid bg-gray-100 font-semibold">
                    <td class="whitespace-normal border border-black text-right" colspan="5">{{ __('Total Sum') }}:
                        {{ App\Helpers\NumberToWordHelper::convert($totalAmount) }}
                        {{ config('amir.currency') ?? __('Rial') }}
                    </td>
                    <td class="border border-black text-center">{{ formatNumber($totalAmount) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-report-layout>
