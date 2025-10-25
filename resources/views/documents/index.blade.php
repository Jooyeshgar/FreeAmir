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
                <x-button href="{{ route('documents.create') }}" class="btn-primary">
                    {{ __('Create Document') }}
                </x-button>
            </div>

            <form action="{{ route('documents.index') }}" method="GET">
                <div class="mt-4 mb-4 grid grid-cols-6 gap-6">
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="number" value="{{ request('number') }}" placeholder="{{ __('Doc Number') }}" />
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-text-input data-jdp input_name="date" placeholder="{{ __('date') }}" input_value="{{ request('date') }}" input_class="datePicker" />
                    </div>

                    <div class="col-span-6 md:col-span-3">
                        <x-input name="text" value="{{ request('text') }}" placeholder="{{ __('Search by document title or transaction description') }}" />
                    </div>

                    <div class="col-span-2 md:col-span-1 text-center">
                        <input type="submit" value="{{ __('Search') }}" class="btn btn-primary" />
                    </div>
                </div>
            </form>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="p-2 w-12">{{ __('Doc Number') }}</th>
                        <th class="p-2">{{ __('Title') }}</th>
                        <th class="p-2 w-40">{{ __('Sum') }}</th>
                        <th class="p-2 w-40">{{ __('Date') }}</th>
                        <th class="p-2 w-60">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td class="p-2">
                                <a href="{{ route('documents.show', $document->id) }}">
                                    {{ convertToFarsi($document->number) }}
                                </a>
                            </td>

                            <td class="p-2">
                                {{ $document->title ?? $document->transactions->first()?->desc . ' ...' }}
                            </td>

                            <td class="p-2">
                                {{ formatNumber($document->transactions->where('value', '>', 0)->sum('value')) }}
                            </td>

                            <td class="p-2">
                                {{ formatDate($document->date) }}
                            </td>

                            <td class="p-2">
                                <div class="flex gap-2">
                                    <a href="{{ route('documents.show', $document->id) }}" class="btn btn-sm btn-info btn-square" title="{{ __('View') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-sm btn-warning btn-square" title="{{ __('Edit') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('documents.duplicate', $document->id) }}" class="btn btn-sm btn-success btn-square" title="{{ __('Duplicate') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </a>
                                    <form action="{{ route('documents.destroy', $document) }}" method="POST" class="inline-block delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error btn-square" title="{{ __('Delete') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $documents->links() }}
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteForms = document.querySelectorAll('.delete-form');

            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (confirm('{{ __('Are you sure you want to delete this document?') }}')) {
                        this.submit();
                    }
                });
            });
        });
    </script>
</x-app-layout>
