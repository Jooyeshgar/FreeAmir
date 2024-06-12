<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transactions') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="card-actions">
                    <a href="{{ route('transactions.create') }}" class="btn btn-primary">Create transaction</a>
                    <table class="table w-full mt-4 overflow-auto">
                        <thead>
                        <tr>
                            @foreach($cols as $col)
                                <th class="px-4 py-2">{{$col}}</th>
                            @endforeach
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td class="px-4 py-2">{{ $document->id }}</td>
                                <td class="px-4 py-2">{{ $document->number }}</td>
                                <td class="px-4 py-2">{{ $document->title }}</td>
                                <td class="px-4 py-2">{{ $document->created_at }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('transactions.edit', $document->id) }}" class="btn btn-sm btn-info">Edit</a>
                                    <form action="{{ route('transactions.destroy', $document) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error">Delete</button>
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
