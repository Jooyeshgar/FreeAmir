<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg flex-1 overflow-hidden rounded-t-3xl border-8 border-gray-200 border-opacity-85 bg-cover bg-center px-4">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card mt-12 w-full max-w-md bg-white p-7 shadow-xl">
                <div>
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-2xl text-blue-600">✉</div>
                    <h1 class="font-bold text-center">{{ __('Check your email') }}</h1>
                    <p class="mt-2 text-sm">{{ __('A verification link and a 6-digit code to :email has been sent.', ['email' => auth()->user()->email]) }}</p>
                </div>

                <x-show-message-bags />

                <form method="POST" action="{{ route('verification.otp') }}" class="mt-3 flex flex-col items-center">
                    @csrf
                    <div class="flex items-start gap-2">
                        <div x-data="{ otp: @js(toEnglish((string) old('otp', ''))) }">
                            <x-text-input id_input="otp" input_name="otp" x-model="otp" :input_value="old('otp')" :title="__('Verification code')" label_class="text-sm text-slate-600"
                                :placeholder="localizeNumber('000000')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                                aria-describedby="otp-help" input_class="text-center font-mono text-base tracking-[0.25em] direction-ltr"
                                x-on:input="otp = $store.utils.convertToEnglish($event.target.value).replace(/\D/g, '').slice(0, 6)"
                                x-effect="$el.value = $store.utils.localizeNumber(otp)" />
                            @error('otp')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="btn mt-5 p-4 text-sm bg-blue-600 text-white hover:bg-blue-700">{{ __('Verify code') }}</button>
                    </div>
                </form>

                <p class="text-sm text-slate-600 p-2">{{ __('To verify your account without entering a code, use the verification link in the email.') }}</p>

                <div class="flex items-center justify-between gap-3">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm text-blue-600">{{ __('Resend email') }}</button>
                    </form>
                    <a href="{{ route('login') }}" class="btn btn-outline btn-sm">{{ __('Back to Login') }}</a>
                </div>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
