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
                <x-button href="{{ route('transactions.create') }}" class="btn-primary">{{ __('Create Document') }}</x-button>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <th class="p-2 w-12">{{ __('Number') }}</th>
                    <th class="p-2">{{ __('Title') }}</th>
                    <th class="p-2 w-40">{{ __('Sum') }}</th>
                    <th class="p-2 w-40">{{ __('Date') }}</th>
                    <th class="p-2 w-40">{{ __('Action') }}</th>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td class="p-2">{{ $document->number }}</td>
                            <td class="p-2">{{ $document->title }}</td>
                            <td class="p-2">{{ $document->transaction->sum('value') }}</td>
                            <td class="p-2">{{ $document->JalaliDate }}</td>
                            <td class="p-2">
                                <a href="{{ route('transactions.edit', $document->id) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('transactions.destroy', $document) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>
</x-app-layout>
