<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('User Details') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="text-2xl font-bold">{{ $user->name }}</h2>
            <div class="grid grid-cols-2 gap-6">
                <div class="col-span-2 md:col-span-1">
                    <x-input disabled title="{{ __('Email') }}" name="id" :value="$user->email" />
                </div>

                <div class="col-span-2 md:col-span-1">
                    <x-input disabled name="name" id="name" title="{{ __('Name') }}" :value="$user->name"/>
                </div>
            </div>
            <div class="card-actions">
                <a href="{{ route('users.edit', $user) }}"
                    class="text-yellow-600 hover:text-yellow-900 btn btn-pr">{{ __('Edit') }}</a>
                <form action="{{ route('users.destroy', $user) }}" method="post" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="text-red-600 hover:text-red-900 btn btn-pr">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
