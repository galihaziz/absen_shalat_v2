@extends('layouts.app')

@section('title', 'Portal Siswa · Rekap Absensi Solat')

@section('content')
<section class="panel narrow-panel">
    <p class="eyebrow">KARTU SISWA</p>
    <h1>Masuk siswa</h1>
    <form class="stack-form" method="post" action="{{ $loginAction }}">
        @csrf
        <label>Nomor induk<input name="induk" value="{{ old('induk') }}" inputmode="numeric" pattern="[0-9]+" maxlength="30" autocomplete="username" required autofocus></label>
        <label>PIN<input name="pin" type="password" maxlength="64" autocomplete="current-password" required></label>
        <p class="filter-feedback">Gunakan PIN yang diberikan sekolah. Demi keamanan, ubah PIN setelah berhasil masuk.</p>
        <button class="button primary" type="submit">Lihat kartu</button>
    </form>
</section>
@endsection