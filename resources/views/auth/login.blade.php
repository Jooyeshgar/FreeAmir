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
                    <div class="mx-0.5">
                        <label class="form-control w-full max-w-xs">
                            <div class="label">
                                <span class="label-text">{{ __('Username or Email') }}</span>
                            </div>
                            <input type="text" name="login" placeholder="{{ __('Enter your email') }}"
                                class="input input-bordered w-full max-w-xs @if ($errors->first('login')) input-error @endif"
                                value="{{ old('login') }}" autocomplete="username" dir="ltr" />
                            @if ($errors->first('login'))
                                <div class="label">
                                    <span class="label-text-alt text-red-700">{{ $errors->first('login') }}</span>
                                </div>
                            @endif
                        </label>
                    </div>
                    <div class="mx-0.5">
                        <label class="form-control w-full max-w-xs">
                            <div class="label">
                                <span class="label-text">{{ __('Password') }}</span>
                            </div>
                            <input type="password" name="password" placeholder="{{ __('Enter your password') }}"
                                class="input input-bordered w-full max-w-xs @if ($errors->first('password')) input-error @endif"
                                autocomplete="current-password" dir="ltr" />
                            @if ($errors->first('password'))
                                <div class="label">
                                    <span class="label-text-alt text-red-700">{{ $errors->first('password') }}</span>
                                </div>
                            @endif
                        </label>
                    </div>
                    <div class="flex items-center justify-between mt-4 pl-2 ">

                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white  py-2 px-8 rounded">
                            {{ __('Login') }}
                        </button>

                        <button type="button" class="bg-gray-300 hover:bg-gray-400 text-black  py-2 px-8 rounded">
                            {{ __('Forgot Password') }}
                        </button>
                    </div>
                </form>

                @if (config('app.debug'))
                    <div class="mt-6 border-t border-gray-200 pt-4 text-sm text-gray-700">
                        <p class="font-semibold">{{ __('Users Information') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('All user have common password') }} <span class="font-mono">password</span> {{ __('are usable') }}.</p>

                        <p class="mt-1 text-xs text-gray-500">{{ __('For copy user name or user email click the related button.') }}</p>
                        <div class="mt-3 space-y-2">
                            @forelse ($debugUsers as $user)
                                <div class="rounded border border-gray-200 px-3 py-2">
                                    <button type="button" class="btn btn-xs font-mono text-xs" dir="ltr" onclick="navigator.clipboard.writeText(@js($user['name']))">{{ $user['name'] }}</button>
                                    <button type="button" class="btn btn-xs font-mono text-xs" dir="ltr" onclick="navigator.clipboard.writeText(@js($user['email']))">{{ $user['email'] }}</button>
                                </div>
                            @empty
                                <div class="rounded border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-500">{{ __('No users records found.') }}</div>
                            @endforelse
                            <p class="mt-1 text-xs text-gray-500">{{ __('For copy password, click the button below.') }}</p>
                            <div class="rounded border border-gray-200 px-3 py-2">
                                <button type="button" class="btn btn-xs font-mono text-xs" onclick="navigator.clipboard.writeText('password')">{{ 'password' }}</button>
                            </div>
                        </div>
                    </div>
                @endif
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
