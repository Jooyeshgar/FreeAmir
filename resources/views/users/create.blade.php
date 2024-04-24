<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create User') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="post" action="{{ route('users.store') }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-6">
                            <x-form-input title="{{ __('Name') }}" name="name"
                                place-holder="{{ __('Enter your name') }}" :message="$errors->first('name')"
                                value="{{ old('name') }}" />
                            <x-form-input title="{{ __('Email') }}" name="email"
                                place-holder="{{ __('Enter your email') }}" :message="$errors->first('email')"
                                value="{{ old('email') }}" />
                            <x-form-input title="{{ __('Password') }}" name="password"
                                place-holder="{{ __('Enter your password') }}" :message="$errors->first('password')" />
                            <x-form-input title="{{ __('Confirm Password') }}" name="password_confirmation"
                                place-holder="{{ __('Confirm your password') }}" :message="$errors->first('password_confirmation')" />
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="submit"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
