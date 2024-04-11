<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Transactions') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200 overflow-auto">
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
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="px-4 py-2">{{ $transaction->id }}</td>
                                <td class="px-4 py-2">{{ $transaction->subject->name }}</td>
                                <td class="px-4 py-2">{{ $transaction->document_id }}</td>
                                <td class="px-4 py-2">{{ $transaction->user->name }}</td>
                                <td class="px-4 py-2">{{ $transaction->desc }}</td>
                                <td class="px-4 py-2">{{ $transaction->value }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-sm btn-info">Edit</a>
                                    <form action="{{ route('transactions.destroy', $transaction) }}" method="POST" class="inline-block">
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
    </div>
</x-app-layout>
