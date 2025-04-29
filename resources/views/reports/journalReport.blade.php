<x-report-layout :title="__('Journal Report')">
    @php
        $pagecount = count($transactionsChunk);
        $current = 1;
    @endphp

    @if ($pagecount === 0)
        <div class="bg-white px-2 print:pl-8">
            <div class="border border-gray-300 p-4 rounded mb-6 flex">
                <div class="flex-grow text-center mb-4">
                    <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
                    <p class="text-lg">{{ __('Journal Report') }}</p>
                </div>
                <div class="w-1/4 text-sm ">
                    <p>تاریخ گزارش: {{ formatDate(now()) }}</p>
                    <p>صفحه: {{ formatNumber($current) }} از {{ formatNumber($pagecount) }}</p>
                </div>
            </div>
            <table class="w-full mb-6 border-collapse border border-gray-300 rounded">

                <tr class="bg-gray-200">
                    <th class="border border-gray-300 p-2" style="width: 1cm">{{ __('Document') }}</th>
                    <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Date') }}</th>
                    <th class="border border-gray-300 p-2" style="width: 1cm">{{ __('Code') }}</th>
                    <th class="border border-gray-300 p-2">{{ __('Subject') }}</th>
                    <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Debit') }}</th>
                    <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Credit') }}</th>
                </tr>
                <p class="text-center text-gray-500">{{ __('No transactions available.') }}</p>
            </table>
        </div>
    @else
        @foreach ($transactionsChunk as $transactions)
            <div class="bg-white px-2 print:pl-8">
                <div class="border border-gray-300 p-4 rounded mb-6 flex">
                    <div class="flex-grow text-center mb-4">
                        <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
                        <p class="text-lg">{{ __('Journal Report') }}</p>
                    </div>
                    <div class="w-1/4 text-sm ">
                        <p>تاریخ گزارش: {{ formatDate(now()) }}</p>
                        <p>صفحه: {{ formatNumber($current) }} از {{ formatNumber($pagecount) }}</p>
                    </div>
                </div>
                <table class="w-full mb-6 border-collapse border border-gray-300 rounded">

                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2" style="width: 1cm">{{ __('Document') }}</th>
                        <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Date') }}</th>
                        <th class="border border-gray-300 p-2" style="width: 1cm">{{ __('Code') }}</th>
                        <th class="border border-gray-300 p-2">{{ __('Subject') }}</th>
                        <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Debit') }}</th>
                        <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Credit') }}</th>
                    </tr>

                    <tbody style="border-bottom: solid 1px">
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="border p-2" style="text-align: center">{{ formatNumber($transaction->document->number) }}</td>
                                <td class="border p-2" style="text-align: center">{{ formatDate($transaction->document->date) }}</td>
                                <td class="border p-2" style="text-align: center">{{ formatCode($transaction->subject->code) }}</td>
                                <td class="border p-2">{{ $transaction->subject->name }}</td>
                                <td class="border p-2">{{ formatNumber($transaction->value < 0 ? -1 * $transaction->value : 0) }}</td>
                                <td class="border p-2">{{ formatNumber($transaction->value > 0 ? $transaction->value : 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (!$loop->last)
                <div class="break-after-page"></div>
            @endif
            @php
                $current++;
            @endphp
        @endforeach
    @endif
</x-report-layout>
