<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg flex flex-1 flex-col overflow-hidden bg-cover bg-center sm:rounded-t-3xl sm:border-8 sm:border-gray-200 sm:border-opacity-85">
        <div class="flex items-center justify-center px-3 py-6 sm:px-6 sm:py-10 md:py-14">
            <div class="card w-full max-w-md bg-white p-4 shadow-xl sm:p-7">
                <div>
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-2xl text-blue-600">✉</div>
                    <h1 class="font-bold text-center">{{ __('Check your email') }}</h1>
                    <p class="mt-2 text-sm">{{ __('A verification link and a 6-digit code have been sent to :email.', ['email' => auth()->user()->email]) }}</p>
                </div>

                <x-show-message-bags />

                <form method="POST" action="{{ route('verification.otp') }}" class="mt-3">
                    @csrf
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                        @php($oldOtp = is_string(old('otp')) ? old('otp') : '')
                        <div x-data="{ otp: @js(toEnglish($oldOtp)) }">
                            <x-text-input id_input="otp" input_name="otp" x-model="otp" :input_value="$oldOtp" :title="__('Verification code')" label_class="text-sm text-slate-600"
                                :placeholder="localizeNumber('000000')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                                aria-describedby="otp-help" input_class="text-center font-mono text-base tracking-[0.25em] direction-ltr"
                                x-on:input="otp = $store.utils.convertToEnglish($event.target.value).replace(/\D/g, '').slice(0, 6)"
                                x-effect="$el.value = $store.utils.localizeNumber(otp)" />
                            <p id="otp-help" class="mt-1 text-xs text-slate-500">{{ __('Enter the code from your email.') }}</p>
                            @error('otp')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="btn bg-blue-600 p-4 text-sm text-white hover:bg-blue-700 sm:mt-5">{{ __('Verify code') }}</button>
                    </div>
                </form>

                <p class="text-sm text-slate-600 p-2">{{ __('To verify your account without entering a code, use the verification link in the email.') }}</p>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm w-full text-blue-600 sm:w-auto">{{ __('Resend email') }}</button>
                    </form>
                    <a href="{{ route('login') }}" class="btn btn-outline btn-sm w-full sm:w-auto">{{ __('Back to Login') }}</a>
                </div>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
