<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if ($user->hasVerifiedEmail()) {
            return $this->verifiedRedirect($user);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::error('Registration verification notification could not be sent.', ['user_id' => $user->id, 'exception' => $exception]);

            return redirect()->route('verification.notice')->with('error', __('Your account was created, but the verification notification could not be sent. Please try again.'));
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

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->verifiedRedirect($user)->with('success', __('Your account has been verified successfully.'));
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
