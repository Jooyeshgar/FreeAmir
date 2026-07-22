<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $this->normalizeEmail($request);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => __('The provided credentials do not match our records.'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (config('app.email_verification') && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')->with('error', __('Please verify your account before continuing.'));
        }

        if ($user->can('access-super-admin-panel')) {
            return redirect()->intended(route('super-admin.dashboard'));
        }

        if (! $user->companies()->exists()) {
            return redirect()->route('registered-user.company.create');
        }

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate(); // Optional: Invalidate session for added security
        $request->session()->regenerateToken(); // Optional: Regenerate session token for CSRF protection

        return redirect('/login');
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => strtolower(trim($request->input('email'))),
            ]);
        }
    }

    public function locale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:fa,en'],
        ]);

        $request->session()->put('locale', $validated['locale']);
        App::setLocale($validated['locale']);

        return back();
    }
}
