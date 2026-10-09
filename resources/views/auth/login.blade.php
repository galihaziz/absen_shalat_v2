@extends('layouts.app')

@section('title', 'Masuk · Rekap Absensi Solat')

@section('content')
<section class="login-wrap">
    <form class="panel login-form" method="post" action="{{ route('login.store') }}">
        @csrf
        <p class="eyebrow">REKAP ABSENSI SOLAT</p>
        <h1>Masuk ke akun</h1>
        <label>Username<input name="username" value="{{ old('username') }}" required autofocus autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button primary" type="submit">Masuk</button>
        <p><a href="{{ route('password.request') }}">Lupa password admin?</a></p>
    </form>
</section>
@endsection