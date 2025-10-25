@php
    $sumDebt = 0;
    $sumCredit = 0;
    $i = 0;
    $allTransactions = $document->transactions;

    foreach ($allTransactions as $transaction) {
        $sumCredit += $transaction->value > 0 ? $transaction->value : 0;
        $sumDebt += $transaction->value < 0 ? -1 * $transaction->value : 0;
        $transaction->sign = $transaction->value > 0 ? 1 : 0;
        $transaction->absValue = abs($transaction->value);
        $transaction->ledgerSign = $transaction->sign . ($transaction->subject ? $transaction->subject->ledger() : '');
    }

    $allTransactions = $allTransactions->sortByDesc(['sign', 'absValue']);

    $groupedTransactions = $allTransactions->groupBy('ledgerSign')->sortKeys();
@endphp

<div class="bg-white px-2 print:pl-8">
    <table class="w-full border-collapse px-4 border-black max-w-full table-fixed break-inside-avoid-page">
        <thead class="print:table-header-group">
            <tr class="">
                <th class="w-8"></th>
                <th class="w-1/6"></th>
                <th class="w-3/6"></th>
                <th class="w-1/6"></th>
                <th class="w-1/6"></th>
            </tr>
            <tr class="">
                <td colspan="5" class="pt-3">
                    <div class="flex justify-between items-start p-4 border border-black rounded-lg">
                        <div class="text-center w-full">
                            <h1 class="text-xl font-bold">{{ session('active-company-name') }}</h1>
                            <p class="text-lg">سند حسابداری</p>
                        </div>
                        <div class="text-sm text-right w-1/4">
                            <p>تاریخ سند: {{ $document->formatted_date }}</p>
                            <p>شماره سند: {{ convertToFarsi($document->number) }}</p>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="5">&nbsp;</td>
            </tr>
            <tr class="bg-gray-200">
                <th class="border border-black p-2"></th>
                <th class="border border-black p-2">{{ __('Code') }}</th>
                <th class="border border-black p-2">{{ __('Description') }}</th>
                <th class="border border-black p-2">{{ __('Debit') }}</th>
                <th class="border border-black p-2">{{ __('Credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groupedTransactions as $transactions)
                @php
                    $firstTransaction = $transactions->first();
                    $groupAlignLeft = $firstTransaction?->value > 0 ? 'text-left' : '';
                    $itemAlignLeft = '';
                    $itemAlignLeft = $firstTransaction?->value > 0 ? 'text-left' : '';
                @endphp
                <tr>
                    <td class="border-t border-x border-black"></td>
                    <td class="border-t border-x border-black p-2 text-center">
                        <b>{{ formatCode($firstTransaction?->subject?->ledger() ?? '') }}</b>
                    </td>
                    <td class="border-t border-x border-black p-2 {{ $groupAlignLeft }}">
                        <b>{{ $firstTransaction?->subject?->getRoot()?->name }}</b>
                    </td>
                    <td class="border-t border-x border-black p-2"></td>
                    <td class="border-t border-x border-black p-2"></td>
                </tr>
                @foreach ($transactions as $transaction)
                    <tr>
                        @php $i++; @endphp
                        <td class="border-x border-black p-2 text-center">{{ formatNumber($i) }}</td>
                        <td class="border-x border-black p-2 text-left">{{ $transaction->subject ? $transaction->subject->formattedCode() : '' }}</td>
                        <td class="border-x border-black p-2 {{ $itemAlignLeft }}">
                            {{ $transaction->subject?->name }}
                            <small class="block truncate">{{ $transaction->desc }}</small>
                        </td>
                        <td class="border-x border-black p-2">
                            {{ $transaction->value < 0 ? formatNumber($transaction->value * -1) : '' }}
                        </td>
                        <td class="border-x border-black p-2">
                            {{ $transaction->value > 0 ? formatNumber($transaction->value) : '' }}
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot class="print:table-footer-group">
            <tr>
                <td class="border border-black p-2 text-center"></td>
                <td class="border border-black p-2"></td>
                <td class="border border-black p-2 text-left font-bold">{{ __('Total Document:') }}</td>
                <td class="border border-black p-2 font-bold">{{ formatNumber($sumDebt) }}</td>
                <td class="border border-black p-2 font-bold">{{ formatNumber($sumCredit) }}</td>
            </tr>
            <tr>
                <td colspan="5"> </td>
            </tr>
            <tr>
                <td colspan="5">
                    <div class="border border-black text-sm rounded-lg p-4">
                        <p class="mb-2">شرح سند: {{ $document->title }}</p>
                        <div class="flex justify-between text-sm print:flex-wrap">
                            <p>ایجاد کننده: {{ $document->creator->name }}</p>
                            <p>تایید کننده: {{ $document->approver?->name }}</p>
                            <p>تاریخ ایجاد: {{ formatDate($document->created_at) }}</p>
                            <p>تاریخ تایید: {{ formatDate($document->approved_at) }}</p>
                        </div>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
