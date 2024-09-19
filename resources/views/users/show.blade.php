<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('User Details') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="text-2xl font-bold">{{ $user->name }}</h2>
            <p class="text-gray-600">{{ $user->email }}</p>
            <div class="card-actions">
                <a href="{{ route('users.edit', $user) }}" class="text-yellow-600 hover:text-yellow-900">Edit</a>
                <form action="{{ route('users.destroy', $user) }}" method="post" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>