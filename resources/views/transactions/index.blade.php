<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transactions') }}
            @if ($currentSubject)
                - {{ $currentSubject->name }} ({{ $currentSubject->formattedCode() }})
            @endif
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            @if ($currentSubject)
                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-blue-800">{{ __('Filtered by Subject') }}</h3>
                            <p class="text-blue-600">{{ $currentSubject->formattedCode() }} - {{ $currentSubject->name }}</p>
                        </div>
                        <a href="{{ route('transactions.index') }}" class="btn btn-outline btn-sm">
                            {{ __('Clear Filter') }}
                        </a>
                    </div>
                </div>
            @endif
            <form action="{{ route('transactions.index') }}" method="GET" class="mt-4 mb-4">
                <div class="grid grid-cols-12 gap-4 items-end">
                    <!-- Subject Selector -->
                    <div class="col-span-12 lg:col-span-3" x-data="{
                        selectedName: '{{ $currentSubject ? $currentSubject->name : '' }}',
                        selectedCode: '{{ $currentSubject ? $currentSubject->code : '' }}',
                        selectedId: '{{ request('subject_id', '') }}',
                    }">
                        <x-subject-select-box :subjects="$subjects" title="{{ __('Subject') }}" id_field="subject_id" placeholder="{{ __('All Subjects') }}"
                            allSelectable="true" class="w-full" />
                    </div>

                    <!-- Date Range -->
                    <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                        <x-input name="start_date" value="{{ request('start_date') }}" data-jdp class="w-full" placeholder="{{ __('Start date') }}" />
                    </div>
                    <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                        <x-input name="end_date" value="{{ request('end_date') }}" data-jdp class="w-full" placeholder="{{ __('End date') }}" />
                    </div>

                    <!-- Document Number Range -->
                    <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                        <x-input name="start_document_number" value="{{ request('start_document_number') }}" class="w-full"
                            placeholder="{{ __('Document start number') }}" />
                    </div>
                    <div class="col-span-6 sm:col-span-3 lg:col-span-2">
                        <x-input name="end_document_number" value="{{ request('end_document_number') }}" class="w-full" placeholder="{{ __('Document end number') }}" />
                    </div>

                    <!-- Search -->
                    <div class="col-span-12 lg:col-span-3">
                        <x-input name="search" value="{{ request('search') }}" class="w-full" placeholder="{{ __('Search for documents') }}" />
                    </div>

                    <!-- Actions -->
                    <div class="col-span-6 sm:col-span-3 lg:col-span-1 flex gap-2">
                        <button type="submit" class="btn btn-primary w-full">{{ __('Search') }}</button>
                    </div>
                    <div class="col-span-6 sm:col-span-3 lg:col-span-1 flex gap-2">
                        <a href="{{ route('transactions.index') }}" class="btn btn-outline w-full">{{ __('Clear') }}</a>
                    </div>
                </div>
                @pushOnce('scripts')
                    <script type="module">
                        jalaliDatepicker.startWatch();
                    </script>
                @endPushOnce
            </form>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <th class="p-2 w-12">{{ __('ID') }}</th>
                    <th class="p-2 w-16">{{ __('Doc Number') }}</th>
                    <th class="p-2">{{ __('Subject') }}</th>
                    <th class="p-2">{{ __('Description') }}</th>
                    <th class="p-2 w-24">{{ __('Debit') }}</th>
                    <th class="p-2 w-24">{{ __('Credit') }}</th>
                    <th class="p-2 w-32">{{ __('Date') }}</th>
                    <th class="p-2 w-32">{{ __('Action') }}</th>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td class="p-2">{{ $transaction->id }}</td>
                            <td class="p-2">
                                <a href="{{ route('documents.show', $transaction->document->id) }}" class="text-blue-600 hover:text-blue-800">
                                    {{ formatDocumentNumber($transaction->document->number) }}
                                </a>
                            </td>
                            <td class="p-2">
                                {{ $transaction->subject?->code }} - {{ $transaction->subject?->name }}
                            </td>
                            <td class="p-2">{{ $transaction->desc }}</td>
                            <td class="p-2 text-red-600">{{ $transaction->debit }}</td>
                            <td class="p-2 text-green-600">{{ $transaction->credit }}</td>
                            <td class="p-2">{{ formatDate($transaction->document->date) }}</td>
                            <td class="p-2">
                                <a href="{{ route('transactions.show', $transaction->id) }}" class="btn btn-sm btn-info">{{ __('View') }}</a>
                                <a href="{{ route('documents.show', $transaction->document->id) }}" class="btn btn-sm btn-secondary">{{ __('Document') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $transactions->appends(request()->query())->links() }}
        </div>
    </div>
</x-app-layout>
