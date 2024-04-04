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
                {!! $banks->links() !!}
            </div>
        </div>
    </div>
</x-app-layout>
