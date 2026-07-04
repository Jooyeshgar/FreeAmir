<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 border-opacity-85 overflow-hidden">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card w-96 p-7 mt-16 bg-white">
                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Forgot your password?') }}</h1>
                    <p class="text-sm mt-2">{{ __('Enter your email and we will send you a password reset link.') }}</p>
                    <x-show-message-bags />

                    <x-text-input class="mt-2 w-full max-w-xs" input_name="email" id="email" placeHolder="{{ __('Enter your email') }}" title="{{ __('Email') }}" type="email" :input_value="old('email')" />
                    
                    <div class="flex items-center justify-between mt-4 pl-2">
                        <button type="submit" class="btn bg-blue-500 hover:bg-blue-600 text-white max-w-xs">{{ __('Email Password Reset Link') }}</button>
                        <a href="{{ route('login') }}" class="btn bg-gray-300 hover:bg-gray-400 text-black">{{ __('Back to Login') }}</a>
                    </div>
                </form>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
