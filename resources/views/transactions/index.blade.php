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
            <form action="{{ route('transactions.index') }}" method="GET">
                <div class="mt-4 mb-4 grid grid-cols-6 gap-6">
                    <div class="col-span-6 md:col-span-2" x-data="{
                        selectedName: '{{ $currentSubject ? $currentSubject->name : '' }}',
                        selectedCode: '{{ $currentSubject ? $currentSubject->code : '' }}',
                        selectedId: '{{ request('subject_id', '') }}',
                    }">
                        <x-subject-select-box :subjects="$subjects" title="{{ __('Subject') }}" id_field="subject_id" placeholder="{{ __('All Subjects') }}"
                            allSelectable="true" class="w-full">
                        </x-subject-select-box>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="document_number" value="{{ request('document_number') }}" placeholder="{{ __('Doc Number') }}" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-text-input data-jdp input_name="date" placeholder="{{ __('date') }}" input_value="{{ request('date') }}"
                            input_class="datePicker"></x-text-input>
                    </div>
                    <div class="col-span-4 md:col-span-1">
                        <x-input name="text" value="{{ request('text') }}" placeholder="{{ __('Search by description') }}" />
                    </div>
                    <div class="col-span-2 md:col-span-1 text-center">
                        <input type="submit" value="{{ __('Search') }}" class="btn-primary btn" />
                    </div>
                </div>
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
                                {{ $transaction->subject->code }} - {{ $transaction->subject->name }}
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
