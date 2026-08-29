<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg flex flex-1 flex-col overflow-hidden bg-cover bg-center sm:rounded-t-3xl sm:border-8 sm:border-gray-200 sm:border-opacity-85">
        <div class="flex items-center justify-center px-3 py-6 sm:px-6 sm:py-10 md:py-14">
            <div class="card w-full max-w-sm bg-white p-4 shadow-xl sm:p-7">
                <form method="POST" action="{{ route('register.email') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Register') }}</h1>
                    <x-show-message-bags />
                    
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="name" id="name" title="{{ __('Name') }}" placeHolder="{{ __('Enter your name') }}" :input_value="old('name')" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="email" id="email" title="{{ __('Email') }}" placeHolder="{{ __('Enter your email') }}" :type="'email'" :input_value="old('email')" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="password" id="password" title="{{ __('Password') }}" placeHolder="{{ __('Enter your password') }}" :type="'password'" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="password_confirmation" id="password_confirmation" title="{{ __('Confirm Password') }}" placeHolder="{{ __('Confirm your password') }}" :type="'password'" />
                    <button type="submit" class="btn bg-blue-500 hover:bg-blue-600 text-white w-full mt-4 max-w-xs">{{ __('Register') }}</button>
                </form>

                <div class="mt-3">
                    <p class="text-center">{{ __('Already have an account?') }} <a class="text-info" href="{{ route('login') }}">{{ __('Login') }}</a></p>
                </div>
            </div>
        </div>

        @include('auth.partials.footer')
    </div>
</x-login-layout>