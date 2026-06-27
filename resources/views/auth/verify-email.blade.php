<x-login-layout>
    <header class="bg-gray-200 py-2 px-4 flex items-center justify-between">
        <div class="flex items-center">
            <img src="/images/logo.png" alt="Logo" width="50" class="mr-2">
            <h1 class="font-bold">{{ __('Amirs free accounting software') }}</h1>
        </div>
        <div class="language-select">
            <form action="" class="language-picker__form">
                <select name="language" class="locale select pr-10 pl-3 py-2">
                    <option lang="fa" value="fa" selected>{{ __('Farsi') }}</option>
                    <option lang="en" value="en">{{ __('English') }}</option>
                </select>
            </form>
        </div>
    </header>

    <div class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 p-0 border-opacity-85 overflow-hidden">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card w-96 p-7 mt-16 bg-white">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Verify Your Email Address') }}</h1>
                    <p class="text-sm">{{ __('Please verify your email address by clicking on the link that was emailed to you.') }}</p>
                    <x-show-message-bags />

                    <button type="submit" class="btn btn-primary w-full mt-4">{{ __('Resend Verification Email') }}</button>
                </form>
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

</x-login-layout>
