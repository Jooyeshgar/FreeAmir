<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 border-opacity-85 overflow-hidden">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card w-96 p-7 mt-16 bg-white">
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
