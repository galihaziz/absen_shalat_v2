@extends('layouts.app')

@section('title', 'Import Siswa · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">MUTAKHIRKAN DATA</p><h1>Import siswa</h1></div></div>
<section class="panel narrow-panel">
    <p>Unggah workbook lengkap siswa aktif maksimal 10 MB. Setiap sheet dapat mewakili satu rombel; kolom nomor induk, nama, dan jenis kelamin akan dideteksi dari judul kolom.</p>
    <p class="warning-text">Siswa yang tercantum akan diperbarui atau diaktifkan kembali. Siswa yang tidak ada di workbook ditandai sebagai alumni; data dan riwayat absensinya tidak dihapus.</p>
    <form class="stack-form" method="post" enctype="multipart/form-data" action="{{ route('students.import.store') }}">@csrf
        <label>File workbook<input type="file" name="excel" accept=".xlsx,.ods" required></label>
        <button class="button primary" type="submit">Mulai import</button>
    </form>
</section>
@endsection