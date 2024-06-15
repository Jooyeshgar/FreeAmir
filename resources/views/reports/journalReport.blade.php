<!-- resources/views/report.blade.php -->
<x-report-layout :title="__('Journal Report')">
    <table class="transactions">
        <thead>
            <tr>
                <th style="width: 1cm">{{ __('Document') }}</th>
                <th style="width: 2cm">{{ __('Date') }}</th>
                <th style="width: 1cm">{{ __('Code') }}</th>
                <th>{{ __('Subject') }}</th>
                <th style="width: 3cm">{{ __('Debit') }}</th>
                <th style="width: 3cm">{{ __('Credit') }}</th>
            </tr>
        </thead>
        <tbody style="border-bottom: solid 1px">
            @foreach ($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->document->number }}</td>
                    <td>{{ formatDate($transaction->document->date) }}</td>
                    <td>{{ $transaction->subject->code }}</td>
                    <td>{{ $transaction->subject->name }}</td>
                    <td>{{ $transaction->value < 0 ? -1 * $transaction->value : 0 }}</td>
                    <td>{{ $transaction->value > 0 ? $transaction->value : 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-report-layout>
