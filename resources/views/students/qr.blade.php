@extends('layouts.app')

@section('title', 'QR Siswa · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">IDENTITAS SISWA</p><h1>QR code siswa</h1></div><a class="button" href="{{ route('students.index') }}">Kembali ke data siswa</a></div>
<section class="panel qr-student-panel">
    <x-student-qr-card
        :student="$student"
        :photo-src="$photoAvailable ? route('students.photo', $student) : null"
        :qr-src="route('students.qr.svg', $student)"
        :template-src="$templateDataUri"
        :template-card-height-mm="$templateCardHeightMm"
    />
    <div class="actions qr-student-actions"><button class="button primary" type="button" onclick="window.print()">Cetak Kartu</button><a class="button" href="{{ route('students.qr.pdf', $student) }}">Unduh PDF</a></div>
</section>
<x-student-qr-standalone :student="$student" :qr-src="route('students.qr.svg', $student)" />
@if($student->jenis_kelamin === 'P')
    <x-student-qr-standalone
        :student="$student"
        :qr-src="route('students.qr.haid.svg', $student)"
        eyebrow="QR HAID"
        title="QR khusus haid"
        description="Gunakan mode QR Haid di halaman scan, lalu pindai QR siswi."
    />
    <div class="actions qr-student-actions"><a class="button" href="{{ route('students.qr.haid.svg', $student) }}" download="QR-Haid-{{ $student->induk }}.svg">Unduh QR Haid</a></div>
@endif
@endsection