@extends('layouts.app')

@section('title', 'Haid Lebih dari 15 Hari · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">TINDAK LANJUT KEHADIRAN</p><h1>Daftar haid lebih dari 15 hari</h1></div><a class="button" href="{{ route('attendance.index') }}">Kembali ke absensi</a></div>
<section class="panel">
    <p>Daftar ini menampilkan siswi yang catatan haidnya masih berlanjut lebih dari 15 hari kalender. Sabtu dan Minggu ikut dihitung; rangkaian dicatat dari hari haid pertama sampai hari ini berdasarkan status haid pada hari sekolah.</p>
    <p data-progressive-count data-loaded-count="{{ $students->count() }}">{{ $students->count() }} dari {{ $totalStudents }} siswi ditampilkan</p>
    <div class="table-wrap" data-progressive-table data-next-page="{{ $nextPageUrl }}"><table><thead><tr><th class="table-row-number">No.</th><th>Induk</th><th class="sticky-name">Nama</th><th>Kelas</th><th>Mulai haid</th><th>Haid terakhir tercatat</th><th>Durasi kalender</th></tr></thead><tbody data-progressive-rows>
    @include('reports._haid_rows', ['students' => $students, 'startIndex' => 0])
    </tbody></table><div class="student-load-trigger" data-progressive-trigger @if(! $nextPageUrl) hidden @endif><span class="loading-feedback" data-progressive-status role="status" aria-live="polite"></span><button class="button quiet small" type="button" data-progressive-button>Muat siswi berikutnya</button></div></div>
</section>
@endsection
