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
                <div class="grid grid-cols-2 gap-6">
                    <x-form-input title="{{ __('Name') }}" name="name" place-holder="{{ __('Name') }}"
                        :value="old('name', $user->name ?? '')" type="text" />
                    <x-form-input title="{{ __('Email') }}" name="email" place-holder="{{ __('Email') }}"
                        :value="old('email', $user->email ?? '')" type="email" />
                    <x-form-input title="{{ __('Password') }}" name="password" place-holder="{{ __('Password') }}"
                        type="password" />
                    <x-form-input title="{{ __('Confirm Password') }}" name="password_confirmation"
                        place-holder="{{ __('Confirm Password') }}" type="password" />
                </div>

                @can('management.roles.*')
                    <br />
                    <hr>
                    <br />
                    <h3>{{ __('Roles') }}</h3>
                    <div class="grid gap-3 grid-cols-5">
                        @foreach ($roles as $role)
                            <x-checkbox :title="$role->name" name="role[]" :value="$role->name"
                                checked="{{ $user->hasRole($role->name) ? true : false }}" />
                        @endforeach
                    </div>
                @endcan
                <br />
                <hr>
                <br />
                <h3>{{ __('Companies') }}</h3>
                <div class="grid gap-3 grid-cols-5">
                    @foreach ($companies as $company)
                        <x-checkbox :title="$company->name" name="company[]" :value="$company->id"
                            checked="{{ $user->companies->contains($company) ? true : false }}" />
                    @endforeach
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
