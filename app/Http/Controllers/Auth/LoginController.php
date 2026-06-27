<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

            if (! Auth::user()->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            if (Auth::user()->hasVerifiedEmail() && ! Auth::user()->companies()->exists()) {
                abort_if(! app()->isProduction(), 404);

                try {
                    Artisan::call('db:seed');
                } catch (\Exception $e) {
                    return redirect()->route('home')->with('error', __('An error occurred while seeding database.'));
                }
            }

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => __('The provided credentials do not match our records.'),
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate(); // Optional: Invalidate session for added security
        $request->session()->regenerateToken(); // Optional: Regenerate session token for CSRF protection

        return redirect('/login');
    }
}
