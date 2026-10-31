@extends('layouts.app')

@section('title', 'Siswa Alpha · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">TINDAK LANJUT KEHADIRAN</p><h1>Daftar siswa alpha</h1></div><a class="button" href="{{ route('reports.index', request()->query()) }}">Kembali ke laporan</a></div>
<section class="panel"><form class="filters" method="get" data-period-filter>
    <label>Periode<select name="periode"><option value="bulan" @selected($period === 'bulan')>Bulanan</option><option value="minggu" @selected($period === 'minggu')>Mingguan</option></select></label>
    <label data-period-month @if($period === 'minggu') hidden @endif>Bulan<select name="bulan" @disabled($period === 'minggu')>@foreach($monthNames as $number => $name)<option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>@endforeach</select></label><label data-period-month @if($period === 'minggu') hidden @endif>Tahun<input type="number" name="tahun" min="2020" max="2100" value="{{ $year }}" @disabled($period === 'minggu')></label>
    <label data-period-week @if($period !== 'minggu') hidden @endif>Tanggal minggu<input type="date" name="minggu" value="{{ $weekDate }}" @disabled($period !== 'minggu')></label><label>Kelas<select name="kelas"><option value="">Semua kelas</option>@foreach($classes as $class)<option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>@endforeach</select></label>
    <label>Cari siswa<input name="cari" value="{{ $search }}"></label><button class="button primary" type="submit">Tampilkan</button><a class="button quiet filter-reset" href="{{ route('reports.alpha') }}">Reset filter</a>
</form>
@if(request()->query())
    <p class="filter-feedback" role="status">{{ count(request()->query()) }} filter diterapkan. Periode {{ $start->format('d/m/Y') }} sampai {{ $end->format('d/m/Y') }}@if($className !== '') · Kelas {{ $className }}@endif @if($search !== '') · Pencarian “{{ $search }}”@endif.</p>
@endif
</section>
<section class="summary-strip" aria-label="Ringkasan siswa alpha"><div><span>Periode laporan</span><strong>{{ $start->format('d/m/Y') }} – {{ $end->format('d/m/Y') }}</strong></div><div><span>Siswa dengan alpha</span><strong>{{ $totalRows }}</strong></div><div><span>Total alpha</span><strong>{{ $totalAlpha }}</strong></div></section>
<section class="panel"><div class="table-wrap" data-progressive-table data-next-page="{{ $nextPageUrl }}"><table><thead><tr><th class="table-row-number">No.</th><th>Induk</th><th class="sticky-name">Nama</th><th>L/P</th><th>Kelas</th><th>Jumlah alpha</th><th>Tanggal alpha</th></tr></thead><tbody data-progressive-rows>@include('reports._alpha_rows', ['rows' => $rows, 'startIndex' => 0])</tbody></table><div class="student-load-trigger" data-progressive-trigger @if(! $nextPageUrl) hidden @endif><span class="loading-feedback" data-progressive-status role="status" aria-live="polite"></span><button class="button quiet small" type="button" data-progressive-button>Muat siswa berikutnya</button></div></div></section>
@endsection