@extends('layouts.app')

@section('title', 'Laporan · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">RINGKASAN KEHADIRAN</p><h1>Laporan absensi</h1></div><a class="button" href="{{ route('reports.alpha', request()->query()) }}">Lihat siswa alpha</a></div>
<div class="content-grid report-layout">
<aside class="report-controls-column">
@if(in_array(auth()->user()->getAttribute('role'), ['admin', 'kesiswaan'], true))
<section class="panel compact-panel scan-cutoff-panel">
    <div class="section-heading"><div><h2>Jam operasional scan QR</h2><p>Scan hanya mencatat kehadiran pada rentang ini.</p></div><span class="scan-cutoff-current">{{ $scanStartTime && $scanEndTime ? $scanStartTime.' – '.$scanEndTime : 'Belum dibatasi' }}</span></div>
    <form class="filters" method="post" action="{{ route('reports.scan-cutoff.update') }}">
        @csrf
        <label>Mulai<input type="time" name="scan_start" value="{{ $scanStartTime }}" required></label>
        <label>Sampai<input type="time" name="scan_end" value="{{ $scanEndTime }}" required></label>
        <button class="button primary" type="submit">Simpan jam scan</button>
    </form>
</section>
@endif
<section class="panel export-panel"><div class="section-heading"><div><h2>Ekspor Excel</h2><p>Pilih periode dan rombel yang akan diekspor.</p></div></div>
    <form class="filters" method="get" action="{{ route('reports.export') }}" data-period-filter>
        <label>Periode<select name="tipe"><option value="minggu" @selected($period === 'minggu')>Mingguan</option><option value="bulan" @selected($period === 'bulan')>Bulanan</option></select></label>
        <label data-period-week @if($period !== 'minggu') hidden @endif>Tanggal minggu<input type="date" name="minggu" value="{{ $weekDate }}" @disabled($period !== 'minggu') required></label>
        <label data-period-month @if($period === 'minggu') hidden @endif>Bulan<select name="bulan" @disabled($period === 'minggu') required>@foreach($monthNames as $number => $name)<option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>@endforeach</select></label>
        <label data-period-month @if($period === 'minggu') hidden @endif>Tahun<input type="number" name="tahun" min="2020" max="2100" value="{{ $year }}" @disabled($period === 'minggu') required></label>
        <label>Rombel<select name="kelas"><option value="semua" @selected($className === '')>Semua rombel</option>@foreach($classes as $class)<option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>@endforeach</select></label>
        <button class="button primary" type="submit">Unduh Excel</button><a class="button quiet filter-reset" href="{{ route('reports.index') }}">Reset pilihan</a>
    </form>
</section>
<section class="panel compact-panel attendance-delete-panel"><h2>Hapus catatan bulanan</h2><p>Hanya catatan absensi pada bulan dan rombel yang dipilih yang akan dihapus. Data siswa tidak berubah.</p>
    <form class="filters attendance-delete-form" data-attendance-delete data-endpoint="{{ route('attendance.delete') }}">
        <label>Bulan<select name="bulan" required>@foreach($monthNames as $number => $name)<option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>@endforeach</select></label>
        <label>Tahun<input type="number" name="tahun" min="2020" max="2100" value="{{ $year }}" required></label>
        <label>Rombel<select name="kelas" required><option value="semua" @selected($className === '')>Semua rombel</option>@foreach($classes as $class)<option value="{{ $class }}">{{ $class }}</option>@endforeach</select></label>
        <button class="button danger" type="submit">Hapus absensi</button>
    </form>
    <p class="delete-feedback" data-delete-state role="status" aria-live="polite"></p>
</section>
</aside>
<section class="panel report-filter-panel"><form class="filters" method="get" data-period-filter>
    <label>Periode<select name="periode"><option value="bulan" @selected($period === 'bulan')>Bulanan</option><option value="minggu" @selected($period === 'minggu')>Mingguan</option></select></label>
    <label data-period-month @if($period === 'minggu') hidden @endif>Bulan<select name="bulan" @disabled($period === 'minggu')>@foreach($monthNames as $number => $name)<option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>@endforeach</select></label>
    <label data-period-month @if($period === 'minggu') hidden @endif>Tahun<input type="number" name="tahun" min="2020" max="2100" value="{{ $year }}" @disabled($period === 'minggu')></label>
    <label data-period-week @if($period !== 'minggu') hidden @endif>Tanggal minggu<input type="date" name="minggu" value="{{ $weekDate }}" @disabled($period !== 'minggu')></label>
    <label>Kelas<select name="kelas"><option value="">Semua kelas</option>@foreach($classes as $class)<option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>@endforeach</select></label>
    <label>Cari siswa<input name="cari" value="{{ $search }}"></label><button class="button primary" type="submit">Tampilkan</button><a class="button quiet filter-reset" href="{{ route('reports.index') }}">Reset filter</a>
</form>
@if(request()->query())
    <p class="filter-feedback" role="status">{{ count(request()->query()) }} filter diterapkan. Rekap mencakup {{ $start->format('d/m/Y') }} sampai {{ $end->format('d/m/Y') }}@if($className !== '') · Kelas {{ $className }}@endif @if($search !== '') · Pencarian “{{ $search }}”@endif.</p>
@endif
</section>
<section class="summary-strip" aria-label="Ringkasan kehadiran periode terpilih"><div><span>Hari kerja dalam periode</span><strong>{{ $workDays }}</strong></div><div><span>Siswa pada hasil filter</span><strong>{{ $totalRecords }}</strong></div><div><span>Total catatan alpha</span><strong>{{ $totals['alpha'] }}</strong></div><div><span>Rasio kehadiran</span><strong>{{ $overallPercentage }}%</strong></div></section>
<section class="panel report-trend-panel" aria-live="polite">
    <div><p class="eyebrow">PERBANDINGAN TREN</p><h2>Rata-rata hadir per hari kerja</h2><p>Periode ini ({{ $start->format('d/m/Y') }}–{{ $trendEnd->format('d/m/Y') }}): <strong>{{ $currentDailyAverage }}</strong> siswa/hari · Pembanding ({{ $previousStart->format('d/m/Y') }}–{{ $previousEnd->format('d/m/Y') }}): <strong>{{ $previousDailyAverage }}</strong> siswa/hari</p></div>
    @if($trendPercent === null)
        <span class="report-trend-value is-neutral">Belum ada data pembanding yang cukup</span>
    @else
        <span class="report-trend-value {{ $trendPercent >= 0 ? 'is-up' : 'is-down' }}">{{ $trendPercent >= 0 ? 'Naik' : 'Turun' }} {{ abs($trendPercent) }}% per hari</span>
    @endif
</section>
<section class="panel report-chart-panel"><h2>Grafik kehadiran</h2><div class="report-chart" role="img" aria-label="Grafik batang rekap kehadiran">
    @foreach([['label' => 'Hadir', 'key' => 'hadir', 'color' => '#26845d'], ['label' => 'Alpha', 'key' => 'alpha', 'color' => '#b4483d'], ['label' => 'Izin', 'key' => 'izin', 'color' => '#bd8427'], ['label' => 'Sakit', 'key' => 'sakit', 'color' => '#627a9b'], ['label' => 'Haid', 'key' => 'haid', 'color' => '#a65d82']] as $item)
        <div class="report-bar-group"><span class="report-bar-value">{{ $totals[$item['key']] }}</span><div class="report-bar-track"><div class="report-bar" @style(['height: '.round(($totals[$item['key']] / $chartMax) * 100, 1).'%', 'background: '.$item['color']])></div></div><span class="report-bar-label">{{ $item['label'] }}</span></div>
    @endforeach
</div></section>
<section class="panel report-table-panel"><div class="section-heading"><div><h2>Rekap siswa</h2><p>{{ $start->format('d/m/Y') }} – {{ $end->format('d/m/Y') }}</p></div></div>
    <div class="table-wrap"><table><thead><tr><th class="table-row-number">No.</th><th>Induk</th><th class="sticky-name">Nama</th><th>Kelas</th><th>Alpha</th><th>Izin</th><th>Sakit</th><th>Hadir</th><th>Haid</th><th>Scan hadir terakhir</th><th>Kehadiran</th></tr></thead><tbody>
    @forelse($students as $index => $student)<tr @class(['irma-row' => $student['is_irma'], 'nonis-row' => $student['is_nonis']])><td class="table-row-number">{{ $index + 1 }}</td><td>{{ $student['induk'] }}</td><td class="sticky-name">{{ $student['nama'] }}</td><td>{{ $student['kelas'] }}</td><td>{{ $student['alpha'] }}</td><td>{{ $student['izin'] }}</td><td>{{ $student['sakit'] }}</td><td>{{ $student['hadir'] }}</td><td>{{ $student['haid'] }}</td><td>{{ $student['scan_hadir_terakhir'] ?? '-' }}</td><td>{{ $student['percentage'] }}%</td></tr>@empty<tr><td colspan="11" class="empty">Tidak ada data yang cocok. <a href="{{ route('reports.index') }}">Hapus filter laporan</a></td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="panel ranking-panel"><div class="ranking-heading"><div><h2>Alpha terbanyak per rombel</h2><p>{{ $start->format('d/m/Y') }} – {{ $end->format('d/m/Y') }}</p></div></div>
    @if($classRanking->isEmpty())<p class="ranking-empty">Belum ada data rombel.</p>@else
        <div class="ranking-list">@foreach($classRanking->take(3) as $rank => $row)<div class="ranking-item"><span class="ranking-position">#{{ $rank + 1 }}</span><span class="ranking-class"><strong>{{ $row->kelas }}</strong><small>{{ $row->total_siswa }} siswa</small></span><strong class="ranking-alpha">{{ $row->total_alpha }} Alpha</strong></div>@endforeach</div>
        @if($classRanking->count() > 3)<details class="ranking-more"><summary>Lihat rombel lainnya ({{ $classRanking->count() - 3 }})</summary><div class="ranking-list">@foreach($classRanking->slice(3) as $rank => $row)<div class="ranking-item"><span class="ranking-position">#{{ $rank + 1 }}</span><span class="ranking-class"><strong>{{ $row->kelas }}</strong><small>{{ $row->total_siswa }} siswa</small></span><strong class="ranking-alpha">{{ $row->total_alpha }} Alpha</strong></div>@endforeach</div></details>@endif
    @endif
</section>
</div>
@endsection