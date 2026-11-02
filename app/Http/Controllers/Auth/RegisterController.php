<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms_accepted' => ['required', 'accepted'],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.min' => 'Password minimal 8 karakter.',
            'terms_accepted.required' => 'Setujui Syarat & Ketentuan dan Kebijakan Privasi sebelum membuat akun.',
            'terms_accepted.accepted' => 'Setujui Syarat & Ketentuan dan Kebijakan Privasi sebelum membuat akun.',
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->forceFill(['terms_version' => config('legal.terms_version'), 'privacy_version' => config('legal.privacy_version'), 'terms_accepted_at' => now()])->save();

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Akun Pinjemin berhasil dibuat.');
    }
}
