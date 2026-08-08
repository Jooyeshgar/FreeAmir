<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg flex-1 overflow-hidden rounded-t-3xl border-8 border-gray-200 border-opacity-85 bg-cover bg-center px-4">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card mt-12 w-full max-w-md bg-white p-7 shadow-xl">
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-2xl text-blue-600">✉</div>
                    <h1 class="text-xl font-bold">{{ __('Check your email') }}</h1>
                    <p class="mt-2 text-sm text-slate-600">{{ __('We sent a verification link and a 6-digit code to :email.', ['email' => auth()->user()->email]) }}</p>
                </div>

                <x-show-message-bags />

                <form method="POST" action="{{ route('verification.otp') }}" class="mt-6">
                    @csrf
                    <label for="otp" class="mb-2 block text-sm font-semibold">{{ __('Verification code') }}</label>
                    <input id="otp" name="otp" type="text" value="{{ old('otp') }}" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                        class="input input-bordered w-full text-center font-mono text-2xl tracking-[0.55em] direction-ltr"
                        placeholder="000000" aria-describedby="otp-help">
                    <p id="otp-help" class="mt-2 text-xs text-slate-500">{{ __('Enter the code from your email.') }}</p>
                    @error('otp')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                    <button type="submit" class="btn mt-4 w-full bg-blue-600 text-white hover:bg-blue-700">{{ __('Verify code') }}</button>
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
