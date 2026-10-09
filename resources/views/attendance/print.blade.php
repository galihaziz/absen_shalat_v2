@extends('layouts.app')

@section('title', 'Cetak Absensi A4 · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">DOKUMEN CETAK</p><h1>Lembar absensi A4</h1></div></div>
<section class="panel narrow-panel"><p>Pilih tanggal awal. Rentang otomatis mencakup 14 hari kalender; lembar hanya memuat hari kerja dan maksimal 10 kolom tanggal.</p>
    <form class="stack-form" method="get" action="{{ route('attendance.print.download') }}">
        <label>Tanggal mulai<input type="date" name="dari" value="{{ $startDate }}" required></label>
        <label>Format<select name="format"><option value="xlsx" @selected($format === 'xlsx')>Excel (.xlsx)</option><option value="docx" @selected($format === 'docx')>Word (.docx)</option></select></label>
        <button class="button primary" type="submit">Unduh lembar absensi</button>
    </form>
    <div class="print-meta"><span><strong>Periode:</strong> {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} sampai {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</span><span><strong>{{ $classes->count() }}</strong> rombel</span><span><strong>Portrait</strong>, XLSX per angkatan dengan page break per rombel; DOCX satu halaman per rombel.</span></div>
</section>
@endsection