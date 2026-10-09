<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->where('role', 'admin')
            ->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'Jika email terdaftar untuk akun admin, tautan reset password akan dikirim.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! User::query()->where('email', $data['email'])->where('role', 'admin')->exists()) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
            ]);
        }

        $status = Password::reset($data, function (User $user, string $password): void {
            if ($user->role !== 'admin') {
                return;
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password admin berhasil diubah. Silakan masuk dengan password baru.')
            : back()->withInput($request->only('email'))->withErrors([
                'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
            ]);
    }
}
