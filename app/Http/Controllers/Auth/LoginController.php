<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'terms_accepted' => ['required', 'accepted'],
        ], [
            'terms_accepted.required' => 'Setujui Syarat & Ketentuan dan Kebijakan Privasi sebelum masuk.',
            'terms_accepted.accepted' => 'Setujui Syarat & Ketentuan dan Kebijakan Privasi sebelum masuk.',
        ]);
        $credentials = ['email' => $validated['email'], 'password' => $validated['password']];

        $key = 'login:'.hash('sha256', Str::lower(trim($credentials['email'])).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak sesuai.',
            ]);
        }

        RateLimiter::clear($key);

        $user = $request->user();
        if (! $user->terms_accepted_at || $user->terms_version !== config('legal.terms_version') || $user->privacy_version !== config('legal.privacy_version')) {
            $user->forceFill([
                'terms_version' => config('legal.terms_version'),
                'privacy_version' => config('legal.privacy_version'),
                'terms_accepted_at' => now(),
            ])->save();
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('status', 'Selamat datang kembali.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Kamu berhasil keluar.');
    }
}
