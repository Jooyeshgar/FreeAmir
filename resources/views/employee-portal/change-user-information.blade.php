<x-app-layout :title="__('My Information')">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" action="{{ route('employee-portal.change-user-information') }}">
                @csrf
                @method('PUT')
                <x-show-message-bags />

                <h2 class="card-title">{{ __('My Information') }}</h2>
                <div class="grid grid-cols-3 gap-6">
                    <x-input name="first_name" id="first_name" title="{{ __('First Name') }}" :value="old('first_name', $employee->first_name ?? '')" />
                    <x-input name="last_name" id="last_name" title="{{ __('Last Name') }}" :value="old('last_name', $employee->last_name ?? '')" />
                    <x-input name="email" id="email" title="{{ __('Email') }}" :value="old('email', $employee->email ?? '')" />
                    <x-input name="password" id="password" title="{{ __('Password') }}" :type="'password'" />
                    <x-input name="password_confirmation" id="password_confirmation" title="{{ __('Confirm Password') }}" :type="'password'" />
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('hr.employees.index') }}" class="btn btn-ghost">{{ __('cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
