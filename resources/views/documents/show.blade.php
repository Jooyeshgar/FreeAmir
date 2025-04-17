@php
    use App\Helpers\NumberToWordHelper;
@endphp
<x-report-layout :title="__('Document') . ' #' . formatNumber($document->number)">
    @php
        $current = 1;
        $sumDebt = 0;
        $sumCredit = 0;
        $i = 0;
        $allTransactions = $document->transactions;

        foreach ($allTransactions as $transaction) {
            $sumDebt += $transaction->value > 0 ? $transaction->value : 0;
            $sumCredit += $transaction->value < 0 ? -1 * $transaction->value : 0;
            $transaction->sign = $transaction->value > 0 ? 1 : 0;
            $transaction->absValue = abs($transaction->value);
            $transaction->ledgerSign = $transaction->sign . $transaction->subject->ledger();
        }

        $allTransactions = $allTransactions->sortByDesc(['sign', 'absValue']);
        $allTransactions = $allTransactions->groupBy('ledgerSign')->flatten();
        $transactionsChunk = $allTransactions->chunk(11);

        $pagecount = count($transactionsChunk);
    @endphp

    @foreach ($transactionsChunk as $pageTransactions)
        @php
            $pageTransactionsGroup = $pageTransactions->groupBy('ledgerSign');
        @endphp
        <div class="bg-white p-4 mb-4 rounded-lg print:pl-5 break-after-page">
            <div class="border border-black p-4 rounded mb-6 flex">
                <div class="flex-grow text-center mb-4">
                    <h1 class="text-xl font-bold">{{ session('active-company-name') }}</h1>
                    <p class="text-lg">سند حسابداری</p>
                </div>
                <div class="w-1/4 text-sm ">
                    <p>تاریخ سند: {{ $document->formatted_date }}</p>
                    <p>شماره سند: {{ formatNumber($document->number) }}</p>
                    <p>صفحه: {{ formatNumber($current) }} از {{ formatNumber($pagecount) }}</p>
                </div>
            </div>
            <table class="w-full mb-6 border-collapse border border-black max-w-full table-fixed">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-black p-2 w-1/12">{{ __('Row') }}</th>
                        <th class="border border-black p-2 w-1/12">{{ __('Code') }}</th>
                        <th class="border border-black p-2 w-6/12 text-right">{{ __('Description') }}</th>
                        <th class="border border-black p-2 w-2/12">{{ __('Debit') }}</th>
                        <th class="border border-black p-2 w-2/12">{{ __('Credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pageTransactionsGroup as $transactions)
                        @php
                            $firstTransaction = $transactions->first();
                            $alignLeft = $firstTransaction->value < 0 ? 'text-left' : '';
                        @endphp
                        <tr>
                            <td class="border-t border-x border-black"></td>
                            <td class="border-t border-x border-black p-2 text-center"><b><u>{{ $firstTransaction->subject->getRoot()->formattedCode() }}</u></b></td>
                            <td class="border-t border-x border-black p-2 {{ $alignLeft }}">
                                <b><u>{{ $firstTransaction->subject->getRoot()->name }}</u></b><br />
                            </td>
                            <td class="border-t border-x border-black p-2"></td>
                            <td class="border-t border-x border-black p-2"></td>
                        </tr>
                        @foreach ($transactions as $transaction)
                            <tr>
                                @php
                                    $i++;
                                @endphp
                                <td class="border-x border-black p-2 text-center">{{ formatNumber($i) }}</td>
                                <td class="border-x border-black p-2">{{ $transaction->subject->formattedCode() }}</td>
                                <td class="border-x border-black p-2 {{ $alignLeft }}">
                                    {{ $transaction->subject->name }}<br />
                                    <small class="block truncate">{{ $transaction->desc }}</small>
                                </td>
                                <td class="border-x border-black p-2">
                                    {{ $transaction->value > 0 ? formatNumber($transaction->value) : '' }}
                                </td>
                                <td class="border-x border-black p-2">
                                    {{ $transaction->value < 0 ? formatNumber($transaction->value * -1) : '' }}
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="border border-black p-2 text-center"></td>
                        <td class="border border-black p-2"></td>
                        <td class="border border-black p-2 text-left">
                            <b>جمع سند:</b>
                        </td>
                        <td class="border border-black p-2">
                            {{ formatNumber($sumDebt) }}
                        </td>
                        <td class="border border-black p-2">
                            {{ formatNumber($sumCredit) }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            {{-- Footer --}}
            <div class="border border-black p-4 rounded">
                <p class="text-sm mb-2">شرح سند: {{ $document->title }}</p>
                <div class="flex justify-between text-sm">
                    <p>ایجاد کننده: {{ $document->creator->name }}</p>
                    <p>تایید کننده: {{ $document->approver?->name }}</p>
                    <p>تاریخ ایجاد: {{ formatDate($document->created_at) }}</p>
                    <p>تاریخ تایید: {{ formatDate($document->approved_at) }}</p>
                </div>
            </div>
        </div>
        @php
            $current++;
        @endphp
    @endforeach
</x-report-layout>
