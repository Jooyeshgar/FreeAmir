@php
    $sumDebt = 0;
    $sumCredit = 0;
    $i = 0;
    $allTransactions = $document->transactions;

    foreach ($allTransactions as $transaction) {
        $sumDebt += $transaction->value > 0 ? $transaction->value : 0;
        $sumCredit += $transaction->value < 0 ? -1 * $transaction->value : 0;
        $transaction->sign = $transaction->value > 0 ? 1 : 0;
        $transaction->absValue = abs($transaction->value);
        $transaction->ledgerSign = $transaction->sign . $transaction->subject?->ledger();
    }

    $allTransactions = $allTransactions->sortByDesc(['sign', 'absValue']);
    $allTransactions = $allTransactions->groupBy('ledgerSign')->flatten();
    $allTransactions = $allTransactions->values(); // Reset keys
@endphp
<x-report-layout :title="__('Document') . ' #' . formatDocumentNumber($document->number)">
    @include('documents.document')
</x-report-layout>
