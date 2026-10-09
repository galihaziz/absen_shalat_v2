@extends('layouts.app')

@section('title', 'Pilih Kartu Manual · Rekap Absensi Solat')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">KARTU SISWA</p><h1>Pilih kartu manual</h1><p>Pilih maksimal 100 siswa aktif untuk dicetak dalam satu PDF.</p></div>
    <a class="button quiet" href="{{ route('students.index') }}">Kembali ke data siswa</a>
</div>
<section class="panel student-list-panel" data-student-list data-next-page="{{ $students->nextPageUrl() }}">
    <div class="section-heading">
        <h2>Siswa <small data-student-count data-count="{{ $students->count() }}">{{ $students->count() }} ditampilkan</small></h2>
        <div class="actions">
            <form id="studentQrPrintForm" method="post" action="{{ route('students.qr.batch') }}" data-qr-selection-form>
                @csrf
                <button class="button primary" type="submit">Cetak siswa terpilih</button>
            </form>
            <form class="search-form" method="get">
                <input name="cari" value="{{ $search }}" placeholder="Nama, induk, atau kelas">
                <button class="button" type="submit">Cari</button>
                @if($search !== '')<a class="button quiet filter-reset" href="{{ route('students.qr.selection') }}">Reset</a>@endif
            </form>
        </div>
    </div>
    @if($search !== '')<p class="filter-feedback" role="status">Pencarian aktif: “{{ $search }}”.</p>@endif
    @if($errors->has('student_ids'))<p class="filter-feedback" role="alert">{{ $errors->first('student_ids') }}</p>@endif
    <p class="filter-feedback" data-qr-selection-status role="status" aria-live="polite"></p>
    <div class="table-wrap" data-student-table-wrap>
        <table>
            <thead><tr><th>Pilih</th><th>Induk</th><th class="sticky-name">Nama</th><th>Kelas</th></tr></thead>
            <tbody data-student-table-body>@include('students._qr_selection_rows', ['students' => $students])</tbody>
        </table>
        <div class="student-load-trigger" data-student-load-trigger @if(! $students->hasMorePages()) hidden @endif>
            <span class="loading-feedback" data-student-load-status role="status" aria-live="polite"></span>
            <button class="button quiet small" type="button" data-student-load-button>Muat data berikutnya</button>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/student-list.js') }}?v={{ md5_file(public_path('js/student-list.js')) }}" defer></script>
<script>
document.querySelector('[data-qr-selection-form]')?.addEventListener('submit', (event) => {
    const selected = document.querySelectorAll('.student-qr-select:checked');
    const status = document.querySelector('[data-qr-selection-status]');
    if (selected.length === 0) {
        event.preventDefault();
        status.textContent = 'Pilih setidaknya satu siswa terlebih dahulu.';
        return;
    }
    if (selected.length > 100) {
        event.preventDefault();
        status.textContent = 'Maksimal 100 siswa dapat dicetak dalam satu PDF.';
        return;
    }
    status.textContent = '';
});
</script>
@endpush
