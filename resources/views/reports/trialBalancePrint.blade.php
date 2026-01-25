<x-report-layout title="{{ __('Trial Balance') }}">
    <div class="bg-white p-2">
        <h1 class="mr-1 text-xl">
            {{ __('Trial Balance') }}
            @if ($currentParent)
                - {{ $currentParent->name }}
            @else
                - {{ __('General Level') }}
            @endif
        </h1>

        <div class="text-gray-700 text-xm mb-3">
            {{ __('Date range') }}: {{ $start_date ?: __('All') }} {{ $end_date ? ' to ' . $end_date : '' }} |
            {{ __('Document range') }}: {{ __('From') }} {{ $start_document_number ?? 3 }}
            {{ $end_document_number ? ' to ' . $end_document_number : '' }}
            {{ $include_children ? ' | ' . __('Two levels') : '' }}
        </div>

        <table class="border border-gray-300 text-xs w-full">
            <thead>
                <tr>
                    <th class="bg-gray-100 border border-gray-300 text-bold" rowspan="2">{{ __('Code') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold" rowspan="2">{{ __('Name') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold" colspan="2">{{ __('Opening') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold" colspan="2">{{ __('Turnover') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold" colspan="2">{{ __('Balance') }}</th>
                </tr>
                <tr>
                    <th class="bg-gray-100 border border-gray-300 text-bold">{{ __('Debtor') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold">{{ __('Creditor') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold">{{ __('Debtor') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold">{{ __('Creditor') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold">{{ __('Debtor') }}</th>
                    <th class="bg-gray-100 border border-gray-300 text-bold">{{ __('Creditor') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $subject)
                    @php
                        $depth = $subject->getAttribute('depth') ?? 0;
                    @endphp
                    <tr>
                        <td class="border border-gray-300 text-center">{{ $subject->formattedCode() }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">
                            @if ($depth > 0)
                                <span>-</span>
                            @endif
                            {{ $subject->name }}
                        </td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ $subject->opening < 0 ? formatNumber(abs($subject->opening)) : formatNumber(0) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ $subject->opening > 0 ? formatNumber(abs($subject->opening)) : formatNumber(0) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber(abs($subject->turnover_debit)) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber($subject->turnover_credit) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ $subject->balance < 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ $subject->balance > 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="border border-gray-300 pr-1 pd-2" colspan="8">
                            {{ __('No Subjects found with the selected filters.') }}</td>
                    </tr>
                @endforelse
                @if ($subjects->count())
                    <tr class="bg-gray-100 border border-gray-300 font-semibold">
                        <td class="border border-gray-300 pr-1 pd-2" colspan="2">{{ __('Total') }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber($subjects->sum(fn($s) => $s->opening < 0 ? abs($s->opening) : 0)) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber($subjects->sum(fn($s) => $s->opening > 0 ? abs($s->opening) : 0)) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber($subjects->sum(fn($s) => abs($s->turnover_debit))) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber($subjects->sum(fn($s) => $s->turnover_credit)) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber($subjects->sum(fn($s) => $s->balance < 0 ? abs($s->balance) : 0)) }}</td>
                        <td class="border border-gray-300 pr-1 pd-2">{{ formatNumber($subjects->sum(fn($s) => $s->balance > 0 ? abs($s->balance) : 0)) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</x-report-layout>
