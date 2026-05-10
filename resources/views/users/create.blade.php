<x-app-layout :title="__('Create User')">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="post" action="{{ route('users.store') }}">
                @csrf
                <x-show-message-bags />

                <div class="grid grid-cols-2 gap-6">
                    <x-input title="{{ __('Name') }}" name="name" value="{{ old('name') }}" />
                    <x-input title="{{ __('Email') }}" name="email" value="{{ old('email') }}" />
                    <x-input title="{{ __('Password') }}" :type="'password'" name="password" />
                    <x-input title="{{ __('Confirm Password') }}" :type="'password'" name="password_confirmation" />
                </div>
                @can('management.roles.*')
                    <br />
                    <hr>
                    <br />
                    <h3 class="label">{{ __('Roles') }}</h3>
                    <div class="grid gap-3 grid-cols-5">
                        @foreach ($roles as $role)
                            <x-checkbox :title="$role->display_name" name="role[]" :value="$role->name" id="role-{{ $role->id }}" />
                        @endforeach
                    </div>
                @endcan
                <br />
                <hr>
                <br />
                <h3 class="label">{{ __('Companies') }}</h3>
                <div class="grid gap-3 grid-cols-5">
                    @foreach ($companies as $company)
                        <x-checkbox :title="$company->name" name="company[]" :value="$company->id"
                            id="company-{{ $role->id }}" />
                    @endforeach
                </div>
                <div class="flex justify-end mt-4">
                    <div class="mb-6">
                        <button class="btn btn-pr"> {{ __('Create') }} </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
