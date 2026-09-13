<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg flex flex-1 flex-col overflow-hidden bg-cover bg-center sm:rounded-t-3xl sm:border-8 sm:border-gray-200 sm:border-opacity-85">
        <div class="flex items-center justify-center px-3 py-6 sm:px-6 sm:py-10 md:py-14">
            <div class="card w-full max-w-sm bg-white p-4 shadow-xl sm:p-7">
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <h1 class="font-bold text-center">{{ __('Reset Password') }}</h1>
                    <x-show-message-bags />

                    <x-text-input class="mt-3 w-full max-w-xs" input_name="email" id="email" placeHolder="{{ __('Enter your email') }}" title="{{ __('Email') }}" type="email" :input_value="old('email', $email)" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="password" id="password" placeHolder="{{ __('Enter your new password') }}" title="{{ __('New Password') }}" type="password" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="password_confirmation" id="password_confirmation" placeHolder="{{ __('Confirm your new password') }}" title="{{ __('Confirm New Password') }}" type="password" />

                    <button type="submit" class="btn bg-blue-500 hover:bg-blue-600 text-white w-full mt-4 max-w-xs">{{ __('Reset Password') }}</button>
                </form>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
