@extends('layouts.app')

@section('title', 'Atur password baru · Rekap Absensi Solat')

@section('content')
<section class="login-wrap">
    <form class="panel login-form" method="post" action="{{ route('password.update') }}">
        @csrf
        <p class="eyebrow">PEMULIHAN AKUN ADMIN</p>
        <h1>Atur password baru</h1>
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email admin<input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></label>
        <label>Password baru<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Ulangi password baru<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
        <button class="button primary" type="submit">Simpan password baru</button>
    </form>
</section>
@endsection
