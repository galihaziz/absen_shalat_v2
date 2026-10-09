@extends('layouts.app')

@section('title', 'Lupa password · Rekap Absensi Solat')

@section('content')
<section class="login-wrap">
    <form class="panel login-form" method="post" action="{{ route('password.email') }}">
        @csrf
        <p class="eyebrow">PEMULIHAN AKUN ADMIN</p>
        <h1>Lupa password?</h1>
        <p>Masukkan email pemulihan yang terdaftar pada akun admin.</p>
        @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
        <label>Email admin<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></label>
        <button class="button primary" type="submit">Kirim tautan reset</button>
        <p><a href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
    </form>
</section>
@endsection
