@php
    use App\Helpers\NumberToWordHelper;
@endphp
<x-report-layout :title="__('Document') . ' #' . formatNumber($document->number)">
    @php
        $transactions = $document->transactions;
        $transactionsChunk = $transactions->chunk(17);
        $pagecount = count($transactionsChunk);
        $current = 1;
        $sum = 0;
        $i = 0;
    @endphp
    @foreach ($transactionsChunk as $transactions)
        <div class="bg-white p-4 mb-4 rounded-lg print:pr-10">
            <div class="border border-gray-300 p-4 rounded mb-6 flex">
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
            <table class="table-auto w-full mb-6 border-collapse border border-gray-300 max-w-full">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2">ردیف</th>
                        <th class="border border-gray-300 p-2">کد</th>
                        <th class="border border-gray-300 p-2">شرح</th>
                        <th class="border border-gray-300 p-2">بدهکار</th>
                        <th class="border border-gray-300 p-2">بستانکار</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        @php
                            $sum += $transaction->value > 0 ? $transaction->value : 0;
                            $i++;
                        @endphp
                        <tr>
                            <td class="border p-2 text-center">{{ formatNumber($i) }}</td>
                            <td class="border p-2 whitespace-normal">{{ $transaction->subject->formattedName() }}</td>
                            <td class="border p-2 whitespace-normal">{{ $transaction->desc }}</td>
                            <td class="border p-2">{{ $transaction->value >= 0 ? formatNumber($transaction->value) : '' }}</td>
                            <td class="border p-2">{{ $transaction->value < 0 ? formatNumber($transaction->value * -1) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($pagecount == $current)
                <div class="text-right text-sm mb-4">
                    <p>جمع کل: {{ NumberToWordHelper::convert($sum) }}</p>
                </div>
            @endif
            <div class="border border-gray-300 p-4 rounded break-after-page">
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
