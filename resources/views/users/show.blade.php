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
                    <x-input disabled name="name" id="name" title="{{ __('Name') }}" :value="$user->name" />
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-lg font-medium">{{ __('Companies') }}</h3>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse ($user->companies as $company)
                        <div class="p-3 bg-gray-100 rounded-lg">
                            <span class="font-medium">{{ $company->name }}</span>
                        </div>
                    @empty
                        <div class="col-span-full">
                            <p class="text-gray-500">{{ __('No companies assigned') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="card-actions">
                <a href="{{ route('users.edit', $user) }}" class="text-yellow-600 hover:text-yellow-900 btn btn-pr">{{ __('Edit') }}</a>
                <form action="{{ route('users.destroy', $user) }}" method="post" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-900 btn btn-pr">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
