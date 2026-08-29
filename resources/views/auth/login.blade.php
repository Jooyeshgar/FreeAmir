<x-login-layout>
    @include('auth.partials.header')

    <div x-data="loginForm(@js(old('email', '')))" class="login-bg flex flex-1 flex-col overflow-hidden bg-cover bg-center sm:rounded-t-3xl sm:border-8 sm:border-gray-200 sm:border-opacity-85">
        <div class="flex items-center justify-center px-3 py-6 sm:px-6 sm:py-10 md:py-14">
            <div class="card w-full max-w-sm bg-white p-4 shadow-xl sm:p-7">
                <form method="POST" action="{{ route('login') }}" autocomplete="on">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Login') }}</h1>
                    <x-show-message-bags />
                    
                    <x-text-input class="mx-auto mt-1 w-full max-w-xs" :title="__('Email')" input_name="email" id_input="email"
                        type="email" :placeholder="__('Enter your email')" x-model="email" autocomplete="username" required autofocus />
                    <x-text-input class="mx-auto mt-1 w-full max-w-xs" :title="__('Password')" input_name="password" id_input="password"
                        type="password" :placeholder="__('Enter your password')" autocomplete="current-password" required />
                    <div class="mt-3">
                        <input type="hidden" name="remember" value="0">
                        <x-checkbox name="remember" id="remember" value="1" :title="__('Remember Me')" :checked="old('remember', '0')" />
                    </div>
                    <div class="mx-auto mt-4 flex w-full max-w-xs flex-col gap-2 min-[375px]:flex-row min-[375px]:items-center min-[375px]:gap-1">
                        <button type="submit" class="btn bg-blue-500 px-8 py-2 text-white hover:bg-blue-600 min-[375px]:w-[70%] min-[375px]:shrink-0">{{ __('Login') }}</button>
                        <a href="{{ route('password.request') }}" class="btn whitespace-nowrap bg-gray-300 px-4 py-2 text-black hover:bg-gray-400 min-[375px]:min-w-0 min-[375px]:flex-1 min-[375px]:px-1 min-[375px]:text-xs">{{ __('Forgot Password') }}</a>
                    </div>
                </form>

                @if(config('app.registration'))
                    <div class="mt-3">
                        <p class="text-center">{{ __('Don\'t have an account?') }} <a class="text-info" href="{{ route('register') }}">{{ __('Sign up') }}</a></p>
                    </div>
                @endif

                @if (!app()->isProduction())
                    <div class="overflow-x-auto mt-3">
                        <p class="text-sm">{{ __('You can use one of the emails below to log in') }}.</p>
                        <table class="table w-full text-right">
                            <thead>
                                <tr>
                                    <th class="text-right">{{ __('Email') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                <template x-for="(user, index) in demoUsers" :key="index">
                                    <tr class="hover:bg-gray-100 cursor-pointer">
                                        <td class="text-left direction-ltr select-text p-2" x-text="user" @click="selectEmail(user)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-sm mt-1">{!! __('The default password for all users is :password', ['password' => '<strong>password</strong>']) !!}.</p>
                @endif
            </div>
        </div>

        @include('auth.partials.footer')
    </div>
    <script>
        function loginForm(initialEmail = '') {
            return {
                email: initialEmail,
                demoUsers: [
                    'admin@example.com',
                    'accountant@example.com',
                    'seller@example.com'
                ],

                selectEmail(email) {
                    this.email = email;
                }
            }
        }
    </script>
</x-login-layout>
