@extends('layouts.app')

@section('title', 'Masuk · Rekap Absensi Solat')

@section('content')
<section class="login-wrap">
    <form class="panel login-form" method="post" action="{{ route('login.store') }}">
        @csrf
        <p class="eyebrow">REKAP ABSENSI SOLAT</p>
        <h1>Masuk ke akun</h1>
        @if(session('registration_pending'))
            <div class="registration-notice-backdrop" data-registration-notice hidden>
                <section class="registration-notice" role="alertdialog" aria-modal="true" aria-labelledby="registrationNoticeTitle" aria-describedby="registrationNoticeMessage" tabindex="-1">
                    <span class="registration-notice-icon" aria-hidden="true">✓</span>
                    <h2 id="registrationNoticeTitle">Pendaftaran berhasil</h2>
                    <p id="registrationNoticeMessage">{{ session('registration_pending') }}</p>
                    <button class="button primary" type="button" data-registration-notice-close>Oke, mengerti</button>
                </section>
            </div>
        @elseif(session('status'))
            <div class="notice success" role="status">{{ session('status') }}</div>
        @endif
        <label>Username<input name="username" value="{{ old('username') }}" required autofocus autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button primary" type="submit">Masuk</button>
        <p>Belum punya akun petugas? <a href="{{ route('staff.register') }}">Daftar dengan username dan password</a></p>
        <p><a href="{{ route('password.request') }}">Lupa password admin?</a></p>
    </form>
</section>
@endsection