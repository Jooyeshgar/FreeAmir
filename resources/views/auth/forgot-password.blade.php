<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg flex flex-1 flex-col overflow-hidden bg-cover bg-center sm:rounded-t-3xl sm:border-8 sm:border-gray-200 sm:border-opacity-85">
        <div class="flex items-center justify-center px-3 py-6 sm:px-6 sm:py-10 md:py-14">
            <div class="card w-full max-w-sm bg-white p-4 shadow-xl sm:p-7">
                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Forgot your password?') }}</h1>
                    <p class="text-sm mt-2">{{ __('Enter your email and we will send you a password reset link.') }}</p>
                    <x-show-message-bags />

                    <x-text-input class="mt-2 w-full max-w-xs" input_name="email" id="email" placeHolder="{{ __('Enter your email') }}" title="{{ __('Email') }}" type="email" :input_value="old('email')" />
                    
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <button type="submit" class="btn bg-blue-500 text-white hover:bg-blue-600 sm:flex-1">{{ __('Email Password Reset Link') }}</button>
                        <a href="{{ route('login') }}" class="btn bg-gray-300 text-black hover:bg-gray-400 sm:flex-1">{{ __('Back to Login') }}</a>
                    </div>
                </form>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
