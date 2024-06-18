<!-- resources/views/report.blade.php -->
<x-report-layout :title="__('Journal Report')">
    @foreach($transactionsChunk as $transactions)
        <table style="margin-top:5px" class="transactions">

            <tr>
                <th style="width: 1cm">{{ __('Document') }}</th>
                <th style="width: 2cm">{{ __('Date') }}</th>
                <th style="width: 1cm">{{ __('Code') }}</th>
                <th>{{ __('Subject') }}</th>
                <th style="width: 2cm">{{ __('Debit') }}</th>
                <th style="width: 2cm">{{ __('Credit') }}</th>
            </tr>

        <tbody style="border-bottom: solid 1px">
            @foreach ($transactions as $transaction)
                <tr>
                    <td style="text-align: center">{{ $transaction->document->number }}</td>
                    <td style="text-align: center">{{ formatDate($transaction->document->date) }}</td>
                    <td style="text-align: center">{{ formatCode($transaction->subject->code) }}</td>
                    <td>{{ $transaction->subject->name }}</td>
                    <td>{{ formatNumber($transaction->value < 0 ? -1 * $transaction->value : 0) }}</td>
                    <td>{{ formatNumber($transaction->value > 0 ? $transaction->value : 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach
</x-report-layout>
