<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit User') }}
        </h2>
    </x-slot>


    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="post" action="{{ route('users.update', $user) }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-6">
                    <x-form-input title="{{ __('Name') }}" name="name" place-holder="{{ __('Name') }}" :value="old('name', $user->name ?? '')" type="text" />
                    <x-form-input title="{{ __('Email') }}" name="email" place-holder="{{ __('Email') }}" :value="old('email', $user->email ?? '')" type="email" />
                    <x-form-input title="{{ __('Password') }}" name="password" place-holder="{{ __('Password') }}" type="password" />
                    <x-form-input title="{{ __('Confirm Password') }}" name="password_confirmation" place-holder="{{ __('Confirm Password') }}" type="password" />
                    <select name="roles[]" multiple="true" id="">
                        @foreach ($roles as $role)
                            <option {{ in_array($role->id, $user->roles->pluck('id')->toArray()) ? 'selected' : '' }} value="{{ $role->name }}">
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end mt-4">
                    <button type="submit"
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
