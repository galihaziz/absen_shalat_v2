@extends('layouts.app')

@section('title', 'Pengguna · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">AKSES APLIKASI</p><h1>Pengguna</h1></div></div>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($pendingUsers->isNotEmpty())
    <section class="panel user-approval-panel">
        <div class="section-heading"><div><p class="eyebrow">PERLU TINDAKAN</p><h2>Pendaftaran menunggu persetujuan</h2></div><span class="user-approval-count">{{ $pendingUsers->count() }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Aksi</th></tr></thead><tbody>
        @foreach($pendingUsers as $pendingUser)
            <tr>
                <td>{{ $pendingUser->username }}</td><td>{{ $pendingUser->nama }}</td><td>{{ $pendingUser->role }}</td>
                <td class="actions">
                    <form method="post" action="{{ route('users.approve', $pendingUser) }}">@csrf<button class="button primary small" type="submit">Setujui</button></form>
                    <form method="post" action="{{ route('users.reject', $pendingUser) }}" onsubmit="return confirm('Tolak pendaftaran {{ $pendingUser->nama }}?')">@csrf<button class="button danger small" type="submit">Tolak</button></form>
                </td>
            </tr>
        @endforeach
        </tbody></table></div>
    </section>
@endif
<div class="content-grid">
    <section class="panel"><h2>{{ $editing ? 'Ubah pengguna' : 'Tambah pengguna' }}</h2>
        <form class="stack-form" method="post" action="{{ route('users.store') }}">@csrf
            <input type="hidden" name="id" value="{{ old('id', $editing?->id) }}">
            <label>Username<input name="username" value="{{ old('username', $editing?->username) }}" required maxlength="50"></label>
            <label>Nama lengkap<input name="nama" value="{{ old('nama', $editing?->nama) }}" required maxlength="100"></label>
            <label>Role<select name="role">@foreach(['admin' => 'Admin', 'kesiswaan' => 'Kesiswaan', 'absensi' => 'Absensi'] as $value => $label)<option value="{{ $value }}" @selected(old('role', $editing?->role ?? 'absensi') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Email / Gmail<input type="email" name="email" value="{{ old('email', $editing?->email) }}" maxlength="255" autocomplete="email"><small>Wajib untuk Admin dan digunakan untuk reset password. Opsional untuk role lain, tetapi jika diisi harus unik.</small></label>
            <label>Password<input type="password" name="password" minlength="8" {{ $editing ? '' : 'required' }} autocomplete="new-password">@if($editing)<small>Kosongkan jika tidak mengubah password.</small>@endif</label>
            <label>Konfirmasi password<input type="password" name="password_confirmation" minlength="8" {{ $editing ? '' : 'required' }} autocomplete="new-password"></label>
            <div class="actions"><button class="button primary" type="submit">Simpan</button>@if($editing)<a class="button quiet" href="{{ route('users.index') }}">Batal</a>@endif</div>
        </form>
    </section>
    <section class="panel"><div class="section-heading"><h2>Daftar akun</h2><form class="search-form" method="get"><input name="cari" value="{{ $search }}" placeholder="Nama, username, email, role"><button class="button" type="submit">Cari</button></form></div>
        <div class="table-wrap"><table><thead><tr><th class="table-row-number">No.</th><th>Username</th><th class="sticky-name">Nama</th><th>Email / Gmail</th><th>Status</th><th>Role</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>
        @forelse($users as $index => $user)<tr><td class="table-row-number">{{ $index + 1 }}</td><td>{{ $user->username }}</td><td class="sticky-name">{{ $user->nama }}</td><td>{{ $user->email ?? '—' }}</td><td>{{ ['approved' => 'Aktif', 'pending' => 'Menunggu', 'rejected' => 'Ditolak'][$user->approval_status] ?? $user->approval_status }}</td><td>{{ $user->role }}</td><td>{{ $user->created_at }}</td><td class="actions"><a class="button small" href="{{ route('users.index', ['edit' => $user->id]) }}">Ubah</a><form method="post" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus pengguna ini?')">@csrf @method('DELETE')<button class="button danger small" type="submit" @disabled($user->is(auth()->user()))>Hapus</button></form></td></tr>
        @empty<tr><td colspan="8" class="empty">Belum ada pengguna.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
@endsection