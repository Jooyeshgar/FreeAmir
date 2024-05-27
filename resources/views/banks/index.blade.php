<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Banks') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('banks.create') }}" class="btn btn-primary">Create Bank</a>

                <table class="table w-full mt-4 overflow-auto">
                    <thead>
                    <tr>
                        <th class="px-4 py-2">نام</th>
                        <th class="px-4 py-2">Action</th>
                    </tr>
                    </thead>
                    <tbody>

                    @foreach ($banks as $bank)
                        <tr>
                            <td class="px-4 py-2">{{ $bank->name }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('banks.edit', $bank) }}"
                                    class="btn btn-sm btn-info">Edit</a>
                                <form action="{{ route('banks.destroy', $bank) }}" method="POST"
                                        class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                @if ($banks->hasPages())
                    <div class="join">
                        {{-- Previous Page Link --}}
                        @if ($banks->onFirstPage())
                            <input class="join-item btn btn-square" type="radio" disabled>
                        @else
                            <a href="{{ $banks->previousPageUrl() }}" class="join-item btn btn-square">&lsaquo;</a>
                        @endif

                        {{-- Pagination Elements --}}
                        @foreach ($banks->links() as $link)
                            @if (is_string($link))
                                <input class="join-item btn btn-square" type="radio" disabled>
                            @else
                                <a href="{{ $link['url'] }}" class="join-item btn btn-square">{{ $link['label'] }}</a>
                            @endif
                        @endforeach

                        {{-- Next Page Link --}}
                        @if ($banks->hasMorePages())
                            <a href="{{ $banks->nextPageUrl() }}" class="join-item btn btn-square">&rsaquo;</a>
                        @else
                            <input class="join-item btn btn-square" type="radio" disabled>
                        @endif
                    </div>
                @endif
        </div>
    </div>
</x-app-layout>
