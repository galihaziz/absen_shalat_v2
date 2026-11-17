<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('attendance.index') : view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('attendance.index');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            User::query()->create([
                'nama' => $data['nama'],
                'username' => $data['username'],
                'email' => mb_strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => 'absensi',
                'approval_status' => 'pending',
            ]);
        } catch (QueryException) {
            return back()->withInput($request->only('nama', 'username', 'email'))
                ->withErrors(['username' => 'Username atau email sudah digunakan.']);
        }

        return redirect()->route('login')
            ->with('registration_pending', 'Pendaftaran berhasil dikirim. Silakan tunggu konfirmasi admin sebelum login.');
    }
}
