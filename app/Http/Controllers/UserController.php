<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('cari', ''));
        $pendingUsers = User::query()
            ->where('approval_status', 'pending')
            ->orderBy('username')
            ->get();
        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($subquery) => $subquery
                ->where('username', 'like', '%'.$search.'%')
                ->orWhere('nama', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('role', 'like', '%'.$search.'%')))
            ->orderBy('role')->orderBy('username')->get();
        $editing = $request->filled('edit') ? User::query()->find($request->integer('edit')) : null;

        return view('users.index', compact('users', 'pendingUsers', 'editing', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $id = $request->integer('id');
        $user = $id ? User::query()->findOrFail($id) : new User;
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($id ?: null)],
            'nama' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(['admin', 'kesiswaan', 'absensi'])],
            'email' => [
                'required_if:role,admin',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($id ?: null),
            ],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->username = $data['username'];
        $user->nama = $data['nama'];
        $user->role = $data['role'];
        $user->email = isset($data['email']) ? mb_strtolower($data['email']) : null;
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        try {
            $user->save();
        } catch (QueryException) {
            return back()->withInput()->withErrors(['username' => 'Username atau email sudah digunakan.']);
        }

        return redirect()->route('users.index')->with('status', $id ? 'User berhasil diperbarui.' : 'User berhasil ditambahkan.');
    }

    public function approve(User $user, Request $request): RedirectResponse
    {
        abort_unless($user->approval_status === 'pending', 404);

        $user->forceFill([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        return redirect()->route('users.index')->with('status', 'Akun '.$user->nama.' berhasil disetujui.');
    }

    public function reject(User $user, Request $request): RedirectResponse
    {
        abort_unless($user->approval_status === 'pending', 404);

        $user->forceFill([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by' => $request->user()->id,
        ])->save();

        return redirect()->route('users.index')->with('status', 'Pendaftaran '.$user->nama.' ditolak.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User berhasil dihapus.');
    }
}
