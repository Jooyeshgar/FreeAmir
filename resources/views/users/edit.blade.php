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
                    <x-input title="{{ __('Name') }}" name="name" :value="old('name', $user->name ?? '')" type="text" />
                    <x-input title="{{ __('Email') }}" name="email" :value="old('email', $user->email ?? '')" type="email" />
                    <x-input title="{{ __('Password') }}" name="password" type="password" />
                    <x-input title="{{ __('Confirm Password') }}" name="password_confirmation" type="password" />
                </div>

                @can('management.roles.*')
                    <br />
                    <hr>
                    <br />
                    <h3>{{ __('Roles') }}</h3>
                    <div class="grid gap-3 grid-cols-5">
                        @foreach ($roles as $role)
                            <x-checkbox :title="$role->name" name="role[]" :value="$role->name" id="role-{{ $role->id }}"
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
                            id="company-{{ $role->id }}"
                            checked="{{ $user->companies->contains($company) ? true : false }}" />
                    @endforeach
                </div>
                <div class="flex justify-end mt-4">
                    <button type="submit" class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
