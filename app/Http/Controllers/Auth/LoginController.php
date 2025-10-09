<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $companies_id = User::find(Auth::id())->companies->pluck('id')->toArray();
            Artisan::call('backup:company', [
                'company_id' => $companies_id,
                '--public-only' => false,
            ]);

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => __('The provided credentials do not match our records.'),
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $companies_id = User::find(Auth::id())->companies->pluck('id')->toArray();
        Artisan::call('backup:company', [
            'company_id' => $companies_id,
            '--public-only' => false,
        ]);

        Auth::logout();
        $request->session()->invalidate(); // Optional: Invalidate session for added security
        $request->session()->regenerateToken(); // Optional: Regenerate session token for CSRF protection

        return redirect('/login');
    }
}
