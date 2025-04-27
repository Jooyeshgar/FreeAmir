<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transactions') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('documents.create') }}" class="btn-primary">{{ __('Create Document') }}</x-button>
            </div>
            <form action="{{ route('documents.index') }}" method="GET">
                <div class="mt-4 mb-4 grid grid-cols-6 gap-6">
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="number" value="{{ request('number') }}" placeholder="{{ __('Doc Number') }}" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-text-input data-jdp input_name="date" placeholder="{{ __('date') }}" input_value="{{ request('date') }}"
                            input_class="datePicker"></x-text-input>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <x-input name="text" value="{{ request('text') }}" placeholder="{{ __('Search by document title or transaction description') }}" />
                    </div>
                    <div class="col-span-2 md:col-span-1 text-center">
                        <input type="submit" value="{{ __('Search') }}" class="btn-primary btn" />
                    </div>
                </div>
            </form>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <th class="p-2 w-12">{{ __('Doc Number') }}</th>
                    <th class="p-2">{{ __('Title') }}</th>
                    <th class="p-2 w-40">{{ __('Sum') }}</th>
                    <th class="p-2 w-40">{{ __('Date') }}</th>
                    <th class="p-2 w-60">{{ __('Action') }}</th>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td class="p-2"><a href="{{ route('documents.show', $document->id) }}">{{ formatDocumentNumber($document->number) }}</a></td>
                            <td class="p-2">{{ $document->title ?? $document->transactions->first()?->desc . ' ...' }}</td>
                            <td class="p-2">{{ formatNumber($document->transactions->where('value', '>', 0)->sum('value')) }}</td>
                            <td class="p-2">{{ formatDate($document->date) }}</td>
                            <td class="p-2">
                                <a href="{{ route('documents.show', $document->id) }}" class="btn btn-sm btn-info">{{ __('View') }}</a>
                                <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('documents.destroy', $document) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" class="btn-sm btn-error">{{ __('Delete') }}</x-button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $documents->links() }}
        </div>
    </div>
    </div>
</x-app-layout>
