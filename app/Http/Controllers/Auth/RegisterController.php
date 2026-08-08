<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    public function registerWithEmail(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($validated);
        Auth::login($user);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::error('Registration verification notification could not be sent.', ['user_id' => $user->id, 'exception' => $exception]);

            $response = config('app.email_verification') ? redirect()->route('verification.notice') : $this->verifiedRedirect($user);

            return $response->with('error', __('Your account was created, but the verification notification could not be sent. Please try again.'));
        }

        if (! config('app.email_verification')) {
            return $this->verifiedRedirect($user)->with('success', __('Registration completed. Please use the verification notification to verify your account.'));
        }

        return redirect()->route('verification.notice')->with('success', __('Registration completed. Please use the verification notification to verify your account.'));
    }

    public function showVerificationNotice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->verifiedRedirect($user);
        }

        return view('auth.verify-user');
    }

    public function resendVerificationNotification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->verifiedRedirect($user);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::error('Verification notification could not be resent.', ['user_id' => $user->id, 'exception' => $exception]);

            return back()->with('error', __('The verification notification could not be sent. Please try again later.'));
        }

        return back()->with('success', __('A new verification notification has been sent.'));
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->verifiedRedirect($user);
        }

        abort_unless(
            hash_equals((string) $user->getKey(), (string) $request->route('id'))
            && hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')),
            403,
        );

        $this->markVerified($user);
        event(new Verified($user));

        return $this->verifiedRedirect($user)->with('success', __('Your account has been verified successfully.'));
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->verifiedRedirect($user);
        }

        $request->validate(['otp' => ['required', 'string']]);
        $otp = toEnglish($request->otp);

        validator(['otp' => $otp], ['otp' => ['digits:6']])->validate();

        $user->refresh();

        if (! $user->email_verification_otp || ! $user->email_verification_otp_expires_at?->isFuture() || ! Hash::check($otp, $user->email_verification_otp)) {
            return back()->withErrors(['otp' => __('The verification code is invalid or has expired.')]);
        }

        $this->markVerified($user);
        event(new Verified($user));

        return $this->verifiedRedirect($user)->with('success', __('Your account has been verified successfully.'));
    }

    private function markVerified(User $user): void
    {
        $user->forceFill([
            'email_verified_at' => $user->freshTimestamp(),
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ])->save();
    }

    private function verifiedRedirect(User $user): RedirectResponse
    {
        return $user->companies()->exists() ? redirect()->route('home') : redirect()->route('registered-user.company.create');
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => strtolower(trim($request->input('email'))),
            ]);
        }
    }
}
