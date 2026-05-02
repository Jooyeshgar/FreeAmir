<x-login-layout>
    <header class="bg-gray-200 py-2 px-4 flex items-center justify-between">
        <div class="flex items-center">
            <img src="/images/logo.png" alt="Logo" width="50" class="mr-2">
            <h1 class="font-bold">{{ __('Amirs free accounting software') }}</h1>
        </div>
        <div class="language-select">
            <form action="" class="language-picker__form">
                <select name="language" class="locale select mx-5 pr-10 pl-3 py-2">
                    <option lang="fa" value="fa" selected>{{ __('Farsi') }}</option>
                    <option lang="en" value="en">{{ __('English') }}</option>
                </select>
            </form>
        </div>
    </header>

    <div x-data="loginForm()" class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 p-0 border-opacity-85 overflow-hidden">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card w-96 p-7 mt-16 bg-white">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Login') }}</h1>
                    <x-show-message-bags />
                    
                    <x-text-input class="mt-1 w-full max-w-xs" title="{{ __('Email') }}" input_name="email" type="email" placeHolder="{{ __('Enter your email') }}" x-model="email" />
                    <x-text-input class="mt-1 w-full max-w-xs" title="{{ __('Password') }}" input_name="password" type="password" placeHolder="{{ __('Enter your password') }}" />
                    <div class="flex items-center justify-between mt-4 pl-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-8 rounded">{{ __('Login') }}</button>
                        <button type="button" class="bg-gray-300 hover:bg-gray-400 text-black py-2 px-8 rounded">{{ __('Forgot Password') }}</button>
                    </div>
                </form>

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

        <div class="flex justify-center mt-4">
            <div class="flex space-x-4">
                <a href="https://github.com/Jooyeshgar/FreeAmir?tab=GPL-3.0-1-ov-file" class="ml-4 bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    {{ __('Terms of Service') }}
                </a>
                <a href="https://github.com/Jooyeshgar/FreeAmir?tab=GPL-3.0-1-ov-file" class="mx-5 bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    {{ __('Privacy Policy') }}
                </a>
                <a href="https://github.com/Jooyeshgar/FreeAmir/issues" class="bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    {{ __('Need help?') }}
                </a>
            </div>
        </div>
    </div>
    <script>
        function loginForm() {
            return {
                email: '',
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