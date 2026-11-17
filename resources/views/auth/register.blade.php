@extends('layouts.app')

@section('title', 'Daftar Petugas · Rekap Absensi Solat')

@section('content')
<section class="login-wrap">
    <form class="panel login-form" method="post" action="{{ route('staff.register.store') }}">
        @csrf
        <p class="eyebrow">AKUN PETUGAS ABSENSI</p>
        <h1>Daftar akun</h1>
        @if($errors->any())
            <div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <label>Nama lengkap<input name="nama" value="{{ old('nama') }}" required maxlength="100" autocomplete="name"></label>
        <label>Username<input name="username" value="{{ old('username') }}" required maxlength="50" autocomplete="username"></label>
        <label>Email / Gmail<input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="nama@gmail.com"></label>
        <label>Password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Konfirmasi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
        <p class="user-account-note">Akun baru menggunakan role Petugas Absensi dan menunggu persetujuan admin.</p>
        <button class="button primary" type="submit">Daftar</button>
        <p><a href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
    </form>
</section>
@endsection
