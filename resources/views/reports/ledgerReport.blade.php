<x-report-layout :title="__('Ledger Report')">
    @php
        $pagecount = count($transactionsChunk);
        $current = 1;
    @endphp
    @if ($pagecount === 0)
        <div class="bg-white px-2 print:pl-8">
            <div class="flex justify-between items-start p-4 border border-black rounded-lg mb-4">
                <div class="flex-grow text-center">
                    <h1 class="text-xl font-bold">{{ config('active-company-name') }}</h1>
                    <p class="text-lg">{{ __('Ledger Report') }}</p>
                    <p class="text-right">{{ __('Account') }}: {{ $subject->formattedName() }}</p>
                </div>
                <div class="w-1/4 text-sm ">
                    <p>تاریخ گزارش: {{ formatDate(now()) }}</p>
                    <p>صفحه: {{ formatNumber($current) }} از {{ formatNumber($pagecount) }}</p>
                </div>
            </div>
            <table class="w-full border-collapse px-4 border-black max-w-full table-fixed break-inside-avoid-page">
                <tr class="bg-gray-200">
                    <th class="border border-black p-2 w-8">{{ __('Document') }}</th>
                    <th class="border border-black p-2 w-11">{{ __('Date') }}</th>
                    <th class="border border-black p-2">{{ __('Description') }}</th>
                    <th class="border border-black p-2 w-1/6">{{ __('Debit') }}</th>
                    <th class="border border-black p-2 w-1/6">{{ __('Credit') }}</th>
                    <th class="border border-black p-2 w-1/6">{{ __('Balance') }}</th>
                </tr>
            </table>
            <p class="text-center text-gray-500">{{ __('No transactions available.') }}</p>
        </div>
    @else
        @php
            $balance = 0;
        @endphp
        @foreach ($transactionsChunk as $transactions)
            <div class="bg-white px-2 pt-3 print:pl-8">
                <div class="flex justify-between items-start p-4 border border-black rounded-lg mb-4">
                    <div class="flex-grow text-center">
                        <h1 class="text-xl font-bold">{{ config('active-company-name') }}</h1>
                        <p class="text-lg">{{ __('Ledger Report') }}</p>
                        <p class="text-right">{{ __('Account') }}: {{ $subject->formattedName() }}</p>
                    </div>
                    <div class="w-1/4 text-sm ">
                        <p>تاریخ گزارش: {{ formatDate(now()) }}</p>
                        <p>صفحه: {{ formatNumber($current) }} از {{ formatNumber($pagecount) }}</p>
                    </div>
                </div>
                <table class="w-full border-collapse px-4 border-black max-w-full table-fixed break-inside-avoid-page">
                    <tr class="bg-gray-200">
                        <th class="border border-black p-2 w-8">{{ __('Document') }}</th>
                        <th class="border border-black p-2 w-11">{{ __('Date') }}</th>
                        <th class="border border-black p-2">{{ __('Description') }}</th>
                        <th class="border border-black p-2 w-1/6">{{ __('Debit') }}</th>
                        <th class="border border-black p-2 w-1/6">{{ __('Credit') }}</th>
                        <th class="border border-black p-2 w-1/6">{{ __('Balance') }}</th>
                    </tr>

                    <tbody class="border-black" style="border-bottom: solid 1px">

                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="border border-black p-2" style="text-align: center">{{ formatNumber($transaction->document?->number) }}</td>
                                <td class="border border-black p-2" style="text-align: center">{{ formatMinimalDate($transaction->document?->date) }}</td>
                                <td class="border border-black p-2">{{ $transaction->desc }}</td>
                                @php
                                    $debit = $transaction->value < 0 ? -1 * $transaction->value : 0;
                                    $credit = $transaction->value > 0 ? $transaction->value : 0;
                                    $balance += $credit - $debit;
                                @endphp
                                <td class="border border-black p-2">{{ formatNumber($debit) }}</td>
                                <td class="border border-black p-2">{{ formatNumber($credit) }}</td>
                                <td class="border border-black p-2">{{ formatNumber($balance) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @php
                $current++;
            @endphp
        @endforeach
    @endif
</x-report-layout>
