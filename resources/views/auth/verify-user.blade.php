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
                    <div class="mx-auto flex w-full max-w-xs flex-col gap-2 min-[375px]:flex-row min-[375px]:items-start min-[375px]:gap-1">
                        @php($oldOtp = is_string(old('otp')) ? old('otp') : '')
                        <div x-data="{ otp: @js(toEnglish($oldOtp)) }" class="w-full min-[375px]:w-[70%] min-[375px]:shrink-0">
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

                        <button type="submit" class="btn w-full bg-blue-600 p-4 text-sm text-white hover:bg-blue-700 min-[375px]:mt-5 min-[375px]:min-w-0 min-[375px]:flex-1 min-[375px]:px-1 min-[375px]:text-xs">{{ __('Verify code') }}</button>
                    </div>
                </form>

                <p class="text-sm text-slate-600 p-2">{{ __('To verify your account without entering a code, use the verification link in the email.') }}</p>

                <div class="mx-auto flex w-full max-w-xs flex-col gap-2 min-[375px]:flex-row min-[375px]:items-center min-[375px]:gap-1">
                    <form method="POST" action="{{ route('verification.send') }}" class="min-[375px]:w-[70%] min-[375px]:shrink-0">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm w-full text-blue-600">{{ __('Resend email') }}</button>
                    </form>
                    <a href="{{ route('login') }}" class="btn btn-outline btn-sm w-full whitespace-nowrap min-[375px]:min-w-0 min-[375px]:flex-1 min-[375px]:px-1 min-[375px]:text-xs">{{ __('Back to Login') }}</a>
                </div>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
