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
                    <a href="{{ route('reports.trial-balance') . '?parent_id=' . $currentParent->parent_id }}" class="btn btn-outline btn-sm">{{ __('Go Up').' - '.( !is_null($currentParent->parent) ? $currentParent->parent->name : __('General Level') ) }} </a>
                @endif
            </div>
            <form action="{{ route('reports.trial-balance') }}" method="get">
                @if($currentParent)
                    <input type="hidden" name="parent_id" value="{{ $currentParent->id }}">
                @endif

                <div class="grid grid-cols-12 gap-3 items-end">
                    <div class="col-span-6 sm:col-span-3">
                        <x-date-picker name="start_date" value="{{ $start_date }}" class="w-full" placeholder="{{ __('Start date') }}"></x-date-picker>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <x-date-picker name="end_date" value="{{ $end_date }}" class="w-full" placeholder="{{ __('End date') }}"></x-date-picker>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <x-input name="start_document_number" value="{{ $start_document_number ?? 3 }}" class="w-full" placeholder="{{ __('Document start number') }}"></x-input>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <x-input name="end_document_number" value="{{ $end_document_number }}" class="w-full" placeholder="{{ __('Document end number') }}"></x-input>
                    </div>
                    <div class="col-span-12 sm:col-span-3 flex flex-col gap-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="include_children" value="1" class="checkbox checkbox-sm" {{ $include_children ? 'checked' : '' }}>
                            <span>{{ __('Include 2 levels') }}</span>
                        </label>
                    </div>
                    <div class="col-span-12 sm:col-span-3 flex gap-2 sm:justify-end">
                        <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('Search') }}</button>
                        <a href="{{ route('reports.trial-balance') }}" class="btn btn-outline rounded-md">{{ __('Clear') }}</a>
                    </div>
                </div>
            </form>
            <div class="mt-4 border border-gray-300 rounded-md overflow-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr class="bg-base-200">
                            <th rowspan="2" class="px-4 py-2 text-center border-r align-middle border-gray-400">{{ __('Code') }}</th>
                            <th rowspan="2" class="px-4 py-2 text-center border-r align-middle border-gray-400">{{ __('Name') }}</th>
                            <th colspan="2" class="px-4 py-2 text-center border-r border-gray-400">{{ __('Opening') }}</th>
                            <th colspan="2" class="px-4 py-2 text-center border-r border-gray-400">{{ __('Turnover') }}</th>
                            <th colspan="2" class="px-4 py-2 text-center border-gray-400">{{ __('Balance') }}</th>
                        </tr>
                        <tr class="bg-base-200">
                            <th class="px-4 py-2 text-center border-r border-gray-400">{{ __('Debtor') }}</th>
                            <th class="px-4 py-2 text-center border-r border-gray-400">{{ __('Creditor') }}</th>
                            <th class="px-4 py-2 text-center border-r border-gray-400">{{ __('Debtor') }}</th>
                            <th class="px-4 py-2 text-center border-r border-gray-400">{{ __('Creditor') }}</th>
                            <th class="px-4 py-2 text-center border-r border-gray-400">{{ __('Debtor') }}</th>
                            <th class="px-4 py-2 text-center">{{ __('Creditor') }}</th>
                        </tr>
                    </thead>
                    <tbody id="tb-rows">
                        @forelse ($subjects as $index => $subject)
                            @php
                                $depth = $subject->getAttribute('depth') ?? 0;
                            @endphp
                            <tr class="hover" data-lazy-row data-index="{{ $index }}">
                                <td class="border-r border-gray-400 text-center">
                                    <a href="{{ route('transactions.index', ['subject_id' => $subject->id]) }}" class="text-primary hover:underline" title="{{ __('View transactions for this subject') }}">
                                        {{ $subject->formattedCode() }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 border-r border-gray-400">
                                    <div class="flex items-center gap-2" style="padding-left: {{ $depth * 12 }}px;">
                                        @if ($depth > 0)
                                            <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span>
                                        @endif
                                        <a href="{{ route('reports.trial-balance', ['parent_id' => $subject->id]) }}" class="text-primary">{{ $subject->name }}</a>
                                        @if ($subject->subjectable)
                                            <div class="badge badge-primary badge-sm ml-2" title="{{ __('Related to') }}: {{ class_basename($subject->subjectable::class) }}">
                                                <a href="{{ route(model_route($subject->subjectable, 'show'), $subject->subjectable) }}">
                                                {{ __(class_basename($subject->subjectable::class)) }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2 border-r border-gray-400">{{ formatNumber(abs($subject->opening_debit)) }}</td>
                                <td class="px-4 py-2 border-r border-gray-400">{{ formatNumber($subject->opening_credit) }}</td>
                                <td class="px-4 py-2 border-r border-gray-400">{{ formatNumber(abs($subject->turnover_debit)) }}</td>
                                <td class="px-4 py-2 border-r border-gray-400">{{ formatNumber($subject->turnover_credit) }}</td>
                                <td class="px-4 py-2 border-r border-gray-400">{{ $subject->balance < 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                                <td class="px-4 py-2 border-r">{{ $subject->balance > 0 ? formatNumber(abs($subject->balance)) : formatNumber(0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">{{ __('No Subjects found with the selected filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div id="tb-sentinel"></div>
            </div>
            <div class="flex justify-end mt-4">
                <form action="{{ route('reports.trial-balance.print') }}" method="get" target="_blank">
                    @foreach(request()->all() as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <button type="submit" class="btn btn-outline">{{ __('Print') }}</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const rows = Array.from(document.querySelectorAll('[data-lazy-row]'));
            const batchSize = 35;
            let nextIndex = 0;

            rows.forEach(row => row.style.display = 'none');

            const revealNext = () => {
                for (let i = 0; i < batchSize && nextIndex < rows.length; i += 1, nextIndex += 1) {
                    rows[nextIndex].style.display = '';
                }
                if (nextIndex >= rows.length && observer) {
                    observer.disconnect();
                }
            };

            const sentinel = document.getElementById('tb-sentinel');
            let observer = null;

            if (rows.length > 0) {
                revealNext();

                observer = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            revealNext();
                        }
                    });
                });

                observer.observe(sentinel);
            }
        })();
    </script>
</x-app-layout>