@extends('layouts.app')

@section('title', 'Kartu Siswa · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">KARTU SISWA</p><h1>Kartu identitas</h1></div><div class="actions student-portal-actions"><a class="button quiet" href="{{ route('student.portal.pin.edit') }}">Ubah PIN</a><button class="button" type="button" onclick="window.print()">Cetak Kartu</button><a class="button primary" href="{{ route('student.portal.pdf') }}">Unduh PDF</a><form method="post" action="{{ route('student.portal.logout') }}">@csrf<button class="button quiet" type="submit">Keluar</button></form></div></div>
<section class="panel qr-student-panel">
    <x-student-qr-card :student="$student" :photo-src="$photoDataUri" :qr-src="$qrDataUri" :template-src="$templateDataUri" :template-card-height-mm="$templateCardHeightMm" />
</section>
<x-student-qr-standalone :student="$student" :qr-src="$qrDataUri" />
@endsection