<x-login-layout>
    <header class="bg-gray-200 py-2 px-4 flex items-center justify-between">
        <div class="flex items-center">
            <img src="/images/logo.png" alt="Logo" width="50" class="mr-2">
            <h1 class="font-bold">{{ __('Amirs free accounting software') }}</h1>
        </div>
        <div class="language-select">
            <form action="" class="language-picker__form ">
                <select name="select select-bordered mx-2 p-3">
                    <option lang="fa" value="english" selected>فارسی</option>
                    <option lang="en" value="francais">English</option>
                </select>
            </form>
        </div>
    </header>

    <div class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 p-0 border-opacity-85  overflow-hidden   ">
        <div class="flex items-center justify-center  rounded-3xl    ">
            <div class="card w-96 p-7 mt-16	 h-373 bg-white  ">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Login') }}</h1>
                    <x-form-input title="{{ __('Username or Email') }}" name="email" place-holder="{{ __('Enter your email') }}" :message="$errors->first('email')" />

                    <x-form-input title="{{ __('Password') }}" name="password" place-holder="{{ __('Enter your password') }}" :message="$errors->first('password')" type="password" />
                    <div class="flex items-center justify-between mt-4 pl-2 ">

                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white  py-2 px-8 rounded">
                            {{ __('Login') }}
                        </button>

                        <button type="submit" class="bg-gray-300 hover:bg-gray-400 text-black  py-2 px-8 rounded">
                            {{ __('Forgot Password') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="flex justify-center mt-4 ">
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
    </div>

</x-login-layout>
