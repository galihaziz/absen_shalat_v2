@extends('layouts.app')

@section('title', 'Absensi · Rekap Absensi Solat')

@section('content')
<div id="attendanceEndpoints" data-save-url="{{ route('attendance.save') }}" hidden></div>
<div class="page-heading"><div><p class="eyebrow">CATATAN KEHADIRAN</p><h1>Absensi siswa</h1></div><div class="actions attendance-quick-actions"><a class="button primary" href="{{ $selectedClass !== '' ? '#attendanceTable' : '#attendanceFilters' }}">{{ $selectedClass !== '' ? 'Lanjutkan '.$selectedClass : 'Mulai absensi' }}</a><a class="button" href="{{ route('attendance.scan') }}">Scan QR</a><a class="button quiet" href="{{ route('attendance.print') }}">Cetak A4</a></div></div>
<p class="attendance-active-date">Ringkasan hari ini · <time datetime="{{ $today }}">{{ \Illuminate\Support\Carbon::parse($today)->format('d/m/Y') }}</time></p>
<section class="summary-strip attendance-today-summary" aria-label="Ringkasan kehadiran hari ini">
    <div><span>Hadir</span><strong>{{ $todayTotals['hadir'] }}</strong></div>
    <div><span>Belum diisi</span><strong>{{ $todayUnmarkedCount }}</strong></div>
    <div><span>Alpha</span><strong>{{ $todayTotals['alpha'] }}</strong></div>
    <div><span>Haid</span><strong>{{ $todayTotals['haid'] }}</strong></div>
    <div><span>Sudah scan</span><strong>{{ $todayScannedCount }}</strong></div>
</section>
@if($haidOverLimitCount > 0)
    <section class="panel" role="alert">
        <strong>Peringatan: {{ $haidOverLimitCount }} siswi tercatat haid lebih dari 15 hari kalender.</strong>
        <p>Perhitungan tetap memasukkan Sabtu dan Minggu. Silakan tindak lanjuti melalui halaman daftar haid.</p>
        <a class="button" href="{{ route('reports.haid-over-limit') }}">Lihat daftar haid &gt; 15 hari</a>
    </section>
@endif
<details class="panel attendance-progress-panel">
    <summary class="attendance-progress-summary"><span><strong>Progress absensi per kelas</strong><small>{{ $todayStudentCount - $todayUnmarkedCount }} dari {{ $todayStudentCount }} siswa tercatat hari ini · Rincian rombel</small></span><span class="button quiet attendance-progress-toggle"><span class="when-closed">Tampilkan</span><span class="when-open">Sembunyikan</span></span>@if($lastScanAt)<span class="last-scan-indicator">Scan terakhir {{ \Illuminate\Support\Carbon::parse($lastScanAt)->format('H:i') }}</span>@endif</summary>
    @if($classProgress->isEmpty())
        <div class="empty-state attendance-empty"><h2>Belum ada rombel</h2><p>Tambahkan data siswa dan kelas agar progress absensi muncul.</p>@if(in_array(auth()->user()->getAttribute('role'), ['admin', 'kesiswaan'], true))<a class="button" href="{{ route('students.index') }}">Kelola data siswa</a>@endif</div>
    @else
        <div class="filters class-progress-filters">
            <label>Tingkat
                <select data-progress-grade-filter>
                    <option value="">Semua tingkat</option>
                    @foreach($progressGrades as $grade)<option value="{{ $grade }}">Kelas {{ $grade }}</option>@endforeach
                </select>
            </label>
            <label>Jurusan
                <select data-progress-major-filter>
                    <option value="">Semua jurusan</option>
                    @foreach($progressMajors as $major)<option value="{{ $major }}">{{ $major }}</option>@endforeach
                </select>
            </label>
        </div>
        <div class="class-progress-grid" data-class-progress-grid>
            @foreach($classProgress as $progress)
                @php($progressPercent = $progress->total_siswa ? round($progress->tercatat / $progress->total_siswa * 100) : 0)
                <a class="class-progress-card {{ $selectedClass === $progress->kelas ? 'is-selected' : '' }}" data-class-progress-card data-grade="{{ $progress->tingkat }}" data-major="{{ $progress->jurusan }}" href="{{ route('attendance.index', ['bulan' => $month, 'tahun' => $year, 'kelas' => $progress->kelas]) }}">
                    <span class="class-progress-heading"><strong>{{ $progress->kelas }}</strong><span>{{ $progress->tercatat }}/{{ $progress->total_siswa }}</span></span>
                    <span class="class-progress-track" role="progressbar" aria-label="Progress {{ $progress->kelas }}" aria-valuemin="0" aria-valuemax="{{ $progress->total_siswa }}" aria-valuenow="{{ $progress->tercatat }}"><span style="width: {{ $progressPercent }}%"></span></span>
                    <span class="class-progress-action">{{ $selectedClass === $progress->kelas ? 'Lanjutkan kelas' : 'Buka absensi' }}</span>
                </a>
            @endforeach
        </div>
        <p class="empty-state attendance-progress-no-results" data-class-progress-empty hidden>Tidak ada kelas yang cocok dengan filter ini.</p>
    @endif
</details>
<section class="panel" id="attendanceFilters">
    <form class="filters" method="get">
        <label>Bulan<select name="bulan">@foreach($monthNames as $number => $name)<option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>@endforeach</select></label>
        <label>Tahun<input type="number" name="tahun" min="2020" max="2100" value="{{ $year }}"></label>
        <label>Kelas<select name="kelas"><option value="">Pilih kelas</option>@foreach($classes as $class)<option value="{{ $class }}" @selected($selectedClass === $class)>{{ $class }}</option>@endforeach</select></label>
        <label>Cari siswa<input name="cari" value="{{ $search }}" placeholder="Nama siswa"></label>
        <label>Minggu tabel<select name="minggu_tabel"><option value="all">Semua minggu</option>@foreach($weeks as $key => $week)<option value="{{ $key }}" @selected($selectedTableWeek === $key)>{{ $week['label'] }}</option>@endforeach</select></label>
        <button class="button primary" type="submit">Tampilkan</button>
        <a class="button quiet filter-reset" href="{{ route('attendance.index') }}">Reset filter</a>
    </form>
    @if(request()->query())
        <p class="filter-feedback" role="status">{{ count(request()->query()) }} filter diterapkan. Hasil mengikuti bulan, tahun, kelas, pencarian, dan minggu tabel yang dipilih.</p>
    @endif
</section>
@if($selectedClass !== '')
    <div class="table-toolbar"><span data-progressive-count data-loaded-count="{{ $students->count() }}">{{ $students->count() }} siswa dimuat · {{ $start->format('d/m/Y') }} sampai {{ $end->format('d/m/Y') }}</span><span id="saveState" data-state="idle" role="status" aria-live="polite"></span></div>
    <div class="table-wrap attendance-table-wrap" id="attendanceTable" data-progressive-table data-next-page="{{ $students->nextPageUrl() }}"><table class="attendance-table"><thead>
    <tr><th class="no" rowspan="2">NO</th><th class="name" rowspan="2">NAMA</th><th class="gender" rowspan="2">L/P</th>
        @foreach($visibleDates as $dateIndex => $date)
            @php($weekKey = $date->modify('monday this week')->format('Y-m-d'))
            <th class="day">{{ $date->format('d/m') }}<small>{{ ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'][$date->format('N') - 1] }}</small></th>
            @if($date->format('N') === '5' || ($selectedTableWeek !== 'all' && $dateIndex === count($visibleDates) - 1))<th class="week">REKAP MINGGUAN<br><span class="status-a">A</span> / <span class="status-i">I</span> / <span class="status-s">S</span> / <span class="status-h">H</span> / <span class="status-m">M</span></th>@endif
        @endforeach
        <th colspan="5">TOTAL BULANAN</th>
    </tr>
    <tr>
        @foreach($visibleDates as $dateIndex => $date)
            <th class="day">STATUS</th>
            @if($date->format('N') === '5' || ($selectedTableWeek !== 'all' && $dateIndex === count($visibleDates) - 1))<th class="week"><span class="status-a">A</span> / <span class="status-i">I</span> / <span class="status-s">S</span> / <span class="status-h">H</span> / <span class="status-m">M</span></th>@endif
        @endforeach
        <th class="status-a">A</th><th class="status-i">I</th><th class="status-s">S</th><th class="status-h">H</th><th class="status-m">M</th>
    </tr>
    </thead><tbody data-progressive-rows>
    @include('attendance._rows', ['students' => $students, 'attendance' => $attendance, 'attendanceTimes' => $attendanceTimes, 'attendanceTotals' => $attendanceTotals, 'visibleDates' => $visibleDates, 'weeks' => $weeks, 'selectedTableWeek' => $selectedTableWeek])
    </tbody></table><div class="student-load-trigger" data-progressive-trigger @if(! $students->hasMorePages()) hidden @endif><span class="loading-feedback" data-progressive-status role="status" aria-live="polite"></span><button class="button quiet small" type="button" data-progressive-button>Muat siswa berikutnya</button></div></div>
    <p class="legend">Keterangan: <span class="status-empty">- = Belum diisi</span>, <span class="status-h">H = Hadir</span>, <span class="status-a">A = Alpha</span>, <span class="status-i">I = Izin</span>, <span class="status-s">S = Sakit</span>, <span class="status-m">M = Haid</span>. Perubahan tersimpan otomatis.</p>
@else
    <section class="empty-state"><h2>Pilih kelas untuk mulai</h2><p>Absensi ditampilkan pada hari kerja untuk bulan yang dipilih.</p><a class="button primary" href="#attendanceFilters">Pilih kelas</a><a class="button" href="{{ route('attendance.scan') }}">Atau scan QR</a></section>
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/attendance-progress.js') }}?v={{ md5_file(public_path('js/attendance-progress.js')) }}" defer></script>
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const endpoints = document.getElementById('attendanceEndpoints').dataset;
const attendanceTableBody = document.querySelector('[data-progressive-rows]');
let latestSaveSequence = 0;
attendanceTableBody?.querySelectorAll('.attendance-select').forEach((select) => { select.dataset.status = select.value; });
attendanceTableBody?.addEventListener('focusin', (event) => {
    const select = event.target.closest('.attendance-select');
    if (select && select.dataset.status === undefined) select.dataset.status = select.value;
});
attendanceTableBody?.addEventListener('change', async (event) => {
    const select = event.target.closest('.attendance-select');
    if (!select) return;
    const state = document.getElementById('saveState');
    const previousStatus = select.dataset.status ?? select.value;
    const statusToSave = select.value;
    const sequence = ++latestSaveSequence;
    select.dataset.saveSequence = String(sequence);
    state.dataset.state = 'saving';
    state.textContent = 'Menyimpan perubahan...';

    try {
        const response = await fetch(endpoints.saveUrl, {
            method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
            body: JSON.stringify({id_siswa: select.dataset.student, tanggal: select.dataset.date, status: statusToSave})
        });
        if (!response.ok) throw new Error('Attendance save failed');
        if (select.dataset.saveSequence === String(sequence)) select.dataset.status = statusToSave;
        if (sequence === latestSaveSequence) {
            state.dataset.state = 'success';
            state.textContent = 'Perubahan berhasil tersimpan.';
        }
    } catch {
        if (select.dataset.saveSequence === String(sequence)) {
            select.value = previousStatus;
            select.dataset.status = previousStatus;
        }
        if (sequence === latestSaveSequence) {
            state.dataset.state = 'error';
            state.textContent = 'Absensi gagal disimpan. Periksa koneksi lalu coba lagi.';
        }
    }
});
</script>
@endpush