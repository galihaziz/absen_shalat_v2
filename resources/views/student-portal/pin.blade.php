@extends('layouts.app')

@section('title', 'Ubah PIN Siswa · Rekap Absensi Solat')

@section('content')
<section class="panel narrow-panel">
    <p class="eyebrow">KEAMANAN AKUN SISWA</p>
    <h1>Ubah PIN</h1>
    <p>Nomor induk: {{ $student->induk }}</p>
    <form class="stack-form" method="post" action="{{ route('student.portal.pin.update') }}">
        @csrf
        <label>PIN saat ini<input name="current_pin" type="password" minlength="8" maxlength="64" autocomplete="current-password" required autofocus></label>
        <label>PIN baru<input name="pin" type="password" minlength="8" maxlength="64" autocomplete="new-password" required></label>
        <label>Ulangi PIN baru<input name="pin_confirmation" type="password" minlength="8" maxlength="64" autocomplete="new-password" required></label>
        <div class="actions"><button class="button primary" type="submit">Simpan PIN</button><a class="button quiet" href="{{ route('student.portal.card') }}">Kembali ke kartu</a></div>
    </form>
</section>
@endsection
