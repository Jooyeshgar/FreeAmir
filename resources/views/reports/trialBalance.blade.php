<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Trial Balance') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold">
                    @if($currentParent)
                        {{ __('Trial Balance') }} - {{ $currentParent->name }}
                    @else
                        {{ __('Trial Balance at General Level') }}
                    @endif
                </h3>
                @if($currentParent)
                    @php
                        $upUrl = route('reports.trial-balance');
                        if ($currentParent->parent_id) {
                            $upUrl .= '?parent_id=' . $currentParent->parent_id;
                        }
                    @endphp
                    <a href="{{ $upUrl }}" class="btn btn-outline btn-sm">
                        {{ __('Back') }}
                    </a>
                @endif
            </div>
            <form action="{{ route('reports.trial-balance') }}" method="get">
                {{-- Pass parent_id for search --}}

                <div class="flex flex-wrap gap-2 items-center">
                    <div class="shrink-0 w-24 text-sm font-medium text-gray-600">{{ __('Date range :') }}</div>
                    <div class="flex gap-2">
                        <x-date-picker name="start_date" class="w-40" placeholder="{{ __('Start date') }}"></x-date-picker>
                        <x-date-picker name="end_date" class="w-40" placeholder="{{ __('End date') }}"></x-date-picker>
                    </div>
                    <div class="shrink-0 w-24 text-sm font-medium text-gray-600">{{ __('Document Number:') }}</div>
                    <div class="flex gap-2">
                        <x-input name="start_document_number" class="w-40" placeholder="{{ __('Document start number') }}"></x-input>
                        <x-input name="end_document_number" class="w-40" placeholder="{{ __('Document end number') }}"></x-input>
                    </div>
                    <div class="flex gap-2">
                        {{-- Select box: Option for show 4 columns or 2 columns and default is 4 --}}
                        {{-- If columns_number equal to 2, do not show the Balance columns --}}

                        {{-- <select name="columns_number" class="" selected="$columns_number ?? 4">
                            <option value="2">{{ __('Two column') }}</option>
                            <option value="4">{{ __('Four column') }}</option>
                        </select> --}}
                    </div>
                    <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('Search') }}</button>
                </div>
            </form>
            <table class="table table-zebra w-full mt-4 border border-gray-300">
                <thead>
                    <tr class="bg-base-200 ">
                        <th rowspan="2" class="px-4 py-2 text-center border-r align-middle border-gray-400">{{ __('Code') }}</th>
                        <th rowspan="2" class="px-4 py-2 text-center border-r align-middle border-gray-400">{{ __('Name') }}</th>
                        <th colspan="2" class="px-4 py-2 text-center border-r  border-gray-400">{{ __('Turnover') }}</th>
                        <th colspan="2" class="px-4 py-2 text-center border-r border-gray-400">{{ __('Balance') }}</th>
                    </tr>
                    <tr class="bg-base-200">
                        <th class="px-4 py-2 text-center border-r border-gray-400">{{ __('Debtor') }}</th>
                        <th class="px-4 py-2 text-center ">{{ __('Creditor') }}</th>
                        <th class="px-4 py-2 text-center border-r border-gray-400">{{ __('Debtor') }}</th>
                        <th class="px-4 py-2 text-center">{{ __('Creditor') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                        <tr class="hover ">
                            <td class="border-r border-gray-400 text-center">
                                <a href="{{ route('transactions.index', ['subject_id' => $subject->id]) }}" class="text-primary hover:underline" title="{{ __('View transactions for this subject') }}">
                                    {{ $subject->formattedCode() }}
                                </a>
                            </td>
                            <td class="px-4 py-2 border-r border-gray-400">
                                <a href="{{ route('reports.trial-balance', ['parent_id' => $subject->id]) }}" class="text-primary"> {{ $subject->name }}</a>
                                @if ($subject->subjectable)
                                    <div class="badge badge-primary badge-sm ml-2" title="{{ __('Related to') }}: {{ class_basename($subject->subjectable::class) }}">
                                        <a href="{{ route(model_route($subject->subjectable, 'show'), $subject->subjectable) }}">
                                        {{ __(class_basename($subject->subjectable::class)) }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2 border-r border-gray-400">{{ formatNumber(abs($subject->debit)) }}</td>
                            <td class="px-4 py-2 border-r">{{ formatNumber($subject->credit) }}</td>
                            <td class="px-4 py-2 border-r border-gray-400">{{ $subject->balance < 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                            <td class="px-4 py-2 border-r">{{ $subject->balance > 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{-- Add an action on the button below for printing all subjects those are showed in the blade with a header --}}

            {{-- <div class="flex justify-end">
                <form action="{{ route('reports.trialBalance.print') }}" method="get">
                        <button type="submit" name="action" value="print" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
                </form>
            </div> --}}
        </div>
    </div>
</x-app-layout>