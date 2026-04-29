<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        $debugUsers = [];

        if (config('app.debug')) {
            $debugUsers = User::query()->select(['name', 'email'])->orderBy('id')->get()
                ->map(fn (User $user) => [
                    'name' => $user->name,
                    'email' => $user->email,
                ])->values();
        }

        return view('auth.login', compact('debugUsers'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        if (Auth::attempt([$loginField => $credentials['login'], 'password' => $credentials['password']])) {
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'login' => __('The provided credentials do not match our records.'),
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate(); // Optional: Invalidate session for added security
        $request->session()->regenerateToken(); // Optional: Regenerate session token for CSRF protection

        return redirect('/login');
    }
}
