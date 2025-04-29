<x-report-layout :title="__('Ledger Report')">
    @php
        $pagecount = count($transactionsChunk);
        $current = 1;
    @endphp
    @foreach ($transactionsChunk as $transactions)
        <div class="w-full bg-white p-4 rounded-lg shadow my-2">
            <div class="border border-gray-300 p-4 rounded mb-6 flex">
                <div class="flex-grow text-center mb-4">
                    <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
                    <p class="text-lg">{{ __('Ledger Report') }}</p>
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
                    <th class="border border-gray-300 p-2">{{ __('Description') }}</th>
                    <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Debit') }}</th>
                    <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Credit') }}</th>
                    <th class="border border-gray-300 p-2" style="width: 2cm">{{ __('Balance') }}</th>
                </tr>

                <tbody style="border-bottom: solid 1px">
                    @php
                        $balance = 0;
                    @endphp
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td class="border p-2" style="text-align: center">{{ formatNumber($transaction->document?->number) }}</td>
                            <td class="border p-2" style="text-align: center">{{ formatDate($transaction->document?->date) }}</td>
                            <td class="border p-2">{{ $transaction->desc }}</td>
                            @php
                                $debit = $transaction->value < 0 ? -1 * $transaction->value : 0;
                                $credit = $transaction->value > 0 ? $transaction->value : 0;
                                $balance += $credit - $debit;
                            @endphp
                            <td class="border p-2">{{ formatNumber($debit) }}</td>
                            <td class="border p-2">{{ formatNumber($credit) }}</td>
                            <td class="border p-2">{{ formatNumber($balance) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @php
            $current++;
        @endphp
    @endforeach
</x-report-layout>
