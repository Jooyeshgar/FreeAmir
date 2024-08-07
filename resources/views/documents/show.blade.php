@php
    use App\Helpers\NumberToWordHelper;
@endphp
<x-print-layout>
    <div class="max-w-2xl mx-auto bg-white p-4 rounded-lg shadow">
        <div class="border border-gray-300 p-4 rounded mb-6 flex">
            <div class="flex-grow text-center mb-4">
                <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
                <p class="text-lg">سند حسابداری</p>
            </div>
            <div class="w-1/4 text-sm ">
                <p>تاریخ سند: {{ $document->formatted_date }}</p>
                <p>شماره سند: {{ formatNumber($document->number) }}</p>
                <p>صفحه: 1 از 1</p>
            </div>
        </div>
        <table class="w-full mb-6 border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border border-gray-300 p-2">کد</th>
                    <th class="border border-gray-300 p-2">شرح</th>
                    <th class="border border-gray-300 p-2">بدهکار</th>
                    <th class="border border-gray-300 p-2">بستانکار</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $sum = 0;
                @endphp
                @foreach ($document->transactions as $transaction)
                    @php
                        $sum += $transaction->value > 0 ? $transaction->value : 0;
                    @endphp
                    <tr>
                        <td class="border border-gray-300 p-2">{{ $transaction->subject->formattedCode() }}</td>
                        <td class="border border-gray-300 p-2">{{ $transaction->desc }}</td>
                        <td class="border border-gray-300 p-2">{{ $transaction->value >= 0 ? formatNumber($transaction->value) : '' }}</td>
                        <td class="border border-gray-300 p-2">{{ $transaction->value < 0 ? formatNumber($transaction->value * -1) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="text-right text-sm mb-4">
            <p>جمع کل: {{ NumberToWordHelper::convert($sum) }}</p>
        </div>
        <div class="border border-gray-300 p-4 rounded">
            <p class="text-sm mb-2">شرح سند: {{ $document->title }}</p>
            <div class="flex justify-between text-sm">
                <p>ایجاد کننده: {{ $document->creator->name }}</p>
                <p>تایید کننده: {{ $document->approver?->name }}</p>
                <p>تاریخ ایجاد: {{ formatDate($document->created_at) }}</p>
                <p>تاریخ تایید: {{ formatDate($document->approved_at) }}</p>
            </div>
        </div>
    </div>
</x-print-layout>
