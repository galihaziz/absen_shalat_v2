@extends('layouts.app')

@section('title', 'Data Siswa · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">DATA REFERENSI</p><h1>Siswa dan rombel</h1></div></div>
<div class="content-grid student-crud-layout">
    <div class="student-management-column">
    <section class="panel management-panel"><details class="management-disclosure" @if($editing) open @endif>
        <summary id="studentCreate" data-student-create>{{ $editing ? 'Ubah data siswa' : 'Tambah siswa' }}@unless($editing)<small>Formulir siswa baru</small>@endunless</summary>
        <form class="stack-form" method="post" action="{{ route('students.store') }}" enctype="multipart/form-data">@csrf
            <input type="hidden" name="id" value="{{ old('id', $editing?->id) }}">
            <label>Nomor induk<input name="induk" value="{{ old('induk', $editing?->induk) }}" inputmode="numeric" required></label>
            <label>Nama lengkap<input name="nama" value="{{ old('nama', $editing?->nama) }}" required></label>
            <label>Jenis kelamin<select name="jenis_kelamin"><option value="L" @selected(old('jenis_kelamin', $editing?->jenis_kelamin ?? 'L') === 'L')>Laki-laki</option><option value="P" @selected(old('jenis_kelamin', $editing?->jenis_kelamin) === 'P')>Perempuan</option></select></label>
            <label>Kelas<select name="id_kelas" required><option value="">Pilih kelas</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected((string) old('id_kelas', $editing?->id_kelas) === (string) $class->id)>{{ $class->nama }}</option>@endforeach</select></label>
            <label>Kategori<select name="kategori" required>
                <option value="biasa" @selected(old('kategori', $editing?->is_irma ? 'irma' : ($editing?->is_nonis ? 'nonis' : 'biasa')) === 'biasa')>Umum</option>
                <option value="irma" @selected(old('kategori', $editing?->is_irma ? 'irma' : ($editing?->is_nonis ? 'nonis' : 'biasa')) === 'irma')>IRMA</option>
                <option value="nonis" @selected(old('kategori', $editing?->is_irma ? 'irma' : ($editing?->is_nonis ? 'nonis' : 'biasa')) === 'nonis')>Nonis</option>
            </select></label>
            @if($editing && $editingPhotoAvailable)
                <div class="student-photo-edit"><img class="student-edit-photo" src="{{ route('students.photo', $editing) }}" alt="Foto {{ $editing->nama }}"><label class="check-label"><input type="checkbox" name="hapus_foto" value="1" @checked(old('hapus_foto'))> Hapus foto saat disimpan</label></div>
            @elseif($editing)
                <p class="delete-feedback">Foto siswa belum tersedia.</p>
            @endif
            <label>Foto siswa (opsional)<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></label>
            <div class="actions"><button class="button primary" type="submit">{{ $editing ? 'Simpan perubahan' : 'Tambah siswa' }}</button>@if($editing)<a class="button quiet" href="{{ route('students.index') }}">Batal</a>@endif</div>
        </form>
    </details></section>
    <section class="panel management-panel class-panel"><details class="management-disclosure">
        <summary>Kelola rombel <small>{{ $classes->count() }} rombel</small></summary>
        <form class="inline-form" method="post" action="{{ route('classes.store') }}">@csrf<label class="grow">Nama rombel<input name="nama" required maxlength="50" placeholder="Contoh: X IPA 1"></label><button class="button primary" type="submit">Tambah</button></form>
        <ul class="class-list" id="classList">
            @forelse($classes as $class)
                <li @if($loop->index >= 5) hidden data-class-list-extra @endif>
                    <span>{{ $class->nama }} <small>{{ $class->students_count }} siswa</small></span>
                    <form method="post" action="{{ route('classes.destroy', $class) }}" onsubmit="return confirm('Hapus rombel ini?')">@csrf @method('DELETE')<button class="button danger small" type="submit">Hapus</button></form>
                </li>
            @empty
                <li>Belum ada rombel.</li>
            @endforelse
        </ul>
        @if($classes->count() > 5)
            <button class="button quiet class-list-toggle" type="button" aria-controls="classList" aria-expanded="false" data-class-list-toggle data-list-id="classList" data-label-collapsed="Tampilkan semua ({{ $classes->count() }})" data-label-expanded="Tampilkan lebih sedikit">Tampilkan semua ({{ $classes->count() }})</button>
        @endif
    </details></section>
    <section class="panel compact-panel">
        <h2>Foto siswa</h2>
        <form class="filters" method="post" action="{{ route('students.photo.store') }}" enctype="multipart/form-data">
            @csrf
            <label>Nomor induk<input name="induk" inputmode="numeric" pattern="[0-9]+" maxlength="30" required></label>
            <label>Foto siswa<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required></label>
            <button class="button primary" type="submit">Simpan foto</button>
        </form>
    </section>
    </div>
    <section class="panel student-list-panel" data-student-list data-next-page="{{ $students->nextPageUrl() }}">
    <div class="bulk-card-toolbar">
        <div><h2>Unduh kartu massal</h2><p>Pilih rombel, tingkat kelas, atau jurusan. Maksimal 100 kartu per PDF.</p></div>
        <form class="filters bulk-card-form" method="post" action="{{ route('students.qr.mass') }}">
            @csrf
            @error('scope')<p class="filter-feedback" role="alert">{{ $message }}</p>@enderror
            <label>Kelompok
                <select name="scope" data-qr-bulk-scope>
                    <option value="class">Per kelas</option>
                    <option value="grade">Per tingkat</option>
                    <option value="major">Per jurusan</option>
                </select>
            </label>
            <label data-qr-bulk-target="class">Kelas
                <select name="classroom_id">@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->nama }}</option>@endforeach</select>
            </label>
            <label data-qr-bulk-target="grade" hidden>Tingkat
                <select name="grade" disabled>@foreach($grades as $grade)<option value="{{ $grade }}">Kelas {{ $grade }}</option>@endforeach</select>
            </label>
            <label data-qr-bulk-target="major" hidden>Jurusan
                <select name="major" disabled>@foreach($majors as $major)<option value="{{ $major }}">{{ $major }}</option>@endforeach</select>
            </label>
            <button class="button primary" type="submit">Unduh kartu</button>
        </form>
    </div>
    <div class="section-heading"><h2>Daftar siswa <small data-student-count data-count="{{ $students->count() }}">{{ $students->count() }} ditampilkan</small></h2><div class="actions"><a class="button quiet" href="{{ route('students.qr.selection') }}">Pilih siswa manual</a><form class="search-form" method="get" data-student-search><input name="cari" value="{{ $search }}" aria-label="Cari siswa berdasarkan nama, nomor induk, atau kelas" placeholder="Nama, nomor induk, atau kelas"><button class="button" type="submit">Cari</button>@if($search !== '')<a class="button quiet filter-reset" href="{{ route('students.index') }}">Reset</a>@endif</form></div></div>
    @if($search !== '')<p class="filter-feedback" role="status">Pencarian aktif: “{{ $search }}”. {{ $matchingStudentCount }} siswa cocok.</p>@endif
    <div class="table-wrap" data-student-table-wrap><table><thead><tr><th>Induk</th><th class="sticky-name">Nama</th><th>L/P</th><th>Kelas</th><th>Kategori</th><th>Aksi</th></tr></thead><tbody data-student-table-body>
    @include('students._rows', ['students' => $students])
    </tbody></table><div class="student-load-trigger" data-student-load-trigger @if(! $students->hasMorePages()) hidden @endif><span class="loading-feedback" data-student-load-status role="status" aria-live="polite"></span><button class="button quiet small" type="button" data-student-load-button>Muat data berikutnya</button></div></div>
</section>
</div>
<section class="panel bulk-student-delete-panel">
    <div class="section-heading">
        <div><p class="eyebrow">TINDAKAN PERMANEN</p><h2>Hapus data siswa massal</h2><p>Menghapus siswa, riwayat absensi, dan foto. Termasuk siswa alumni.</p></div>
        <strong class="bulk-delete-count" data-mass-delete-count aria-live="polite"></strong>
    </div>
    <form class="filters bulk-student-delete-form" method="post" action="{{ route('students.destroy.mass') }}" data-mass-delete-form>
        @csrf
        @method('DELETE')
        <label>Kelompok
            <select name="scope" data-mass-delete-scope>
            <option value="class" @selected(old('scope', 'class') === 'class')>Per kelas</option>
            <option value="grade" @selected(old('scope') === 'grade')>Per tingkat</option>
            <option value="major" @selected(old('scope') === 'major')>Per jurusan</option>
            </select>
        </label>
        @foreach(['class', 'grade', 'major'] as $deleteScope)
            <label data-mass-delete-target="{{ $deleteScope }}" @if($deleteScope !== 'class') hidden @endif>
                {{ ['class' => 'Kelas', 'grade' => 'Tingkat', 'major' => 'Jurusan'][$deleteScope] }}
                <select name="{{ ['class' => 'classroom_id', 'grade' => 'grade', 'major' => 'major'][$deleteScope] }}" @disabled($deleteScope !== 'class')>
                    @foreach($massDeleteOptions[$deleteScope] as $option)
                        <option value="{{ $option['value'] }}" data-count="{{ $option['count'] }}" @selected((string) old(['class' => 'classroom_id', 'grade' => 'grade', 'major' => 'major'][$deleteScope]) === (string) $option['value'])>{{ $option['label'] }} · {{ $option['count'] }} siswa</option>
                    @endforeach
                </select>
            </label>
        @endforeach
        <input type="hidden" name="expected_count" data-mass-delete-expected>
        <button class="button danger" type="submit" @disabled($massDeleteOptions['class']->isEmpty())>Hapus kelompok terpilih</button>
    </form>
    @error('scope')<p class="filter-feedback" role="alert">{{ $message }}</p>@enderror
</section>
<section class="panel compact-panel attendance-delete-panel"><h2>Hapus data absensi</h2><p>Hanya catatan absensi pada bulan dan rombel yang dipilih yang akan dihapus. Data siswa tidak berubah.</p>
    <form class="filters attendance-delete-form" data-attendance-delete data-endpoint="{{ route('attendance.delete') }}">
        <label>Bulan<select name="bulan" required>@foreach($monthNames as $number => $name)<option value="{{ $number }}" @selected($attendanceDeleteMonth === $number)>{{ $name }}</option>@endforeach</select></label>
        <label>Tahun<input type="number" name="tahun" min="2020" max="2100" value="{{ $attendanceDeleteYear }}" required></label>
        <label>Rombel<select name="kelas" required><option value="semua">Semua rombel</option>@foreach($classes as $class)<option value="{{ $class->nama }}">{{ $class->nama }}</option>@endforeach</select></label>
        <button class="button danger" type="submit">Hapus absensi</button>
    </form>
    <p class="delete-feedback" data-delete-state role="status" aria-live="polite"></p>
</section>
<dialog class="student-qr-dialog" data-student-qr-dialog aria-labelledby="studentQrDialogTitle">
    <div class="student-qr-dialog-header"><h2 id="studentQrDialogTitle">QR siswa</h2><button class="button small" type="button" data-student-qr-close>Tutup</button></div>
    <div class="student-qr-dialog-content" data-student-qr-content aria-live="polite"></div>
</dialog>
@endsection

@push('scripts')
<script src="{{ asset('js/student-list.js') }}?v={{ md5_file(public_path('js/student-list.js')) }}" defer></script>
<script>
const studentQrDialog = document.querySelector('[data-student-qr-dialog]');
const studentQrDialogContent = studentQrDialog?.querySelector('[data-student-qr-content]');
const studentList = document.querySelector('[data-student-list]');
const qrBulkScope = document.querySelector('[data-qr-bulk-scope]');
const qrBulkTargets = document.querySelectorAll('[data-qr-bulk-target]');

function updateQrBulkTargets() {
    qrBulkTargets.forEach((target) => {
        const active = target.dataset.qrBulkTarget === qrBulkScope?.value;
        target.hidden = !active;
        target.querySelector('select').disabled = !active;
    });
}

qrBulkScope?.addEventListener('change', updateQrBulkTargets);
updateQrBulkTargets();

const massDeleteScope = document.querySelector('[data-mass-delete-scope]');
const massDeleteTargets = document.querySelectorAll('[data-mass-delete-target]');
const massDeleteCount = document.querySelector('[data-mass-delete-count]');
const massDeleteExpected = document.querySelector('[data-mass-delete-expected]');
const massDeleteForm = document.querySelector('[data-mass-delete-form]');

function updateMassDeleteTarget() {
    let selectedOption = null;
    massDeleteTargets.forEach((target) => {
        const active = target.dataset.massDeleteTarget === massDeleteScope?.value;
        target.hidden = !active;
        const select = target.querySelector('select');
        select.disabled = !active;
        if (active) selectedOption = select.selectedOptions[0] || null;
    });
    const count = Number(selectedOption?.dataset.count) || 0;
    massDeleteExpected.value = String(count);
    massDeleteCount.textContent = `${count} siswa akan dihapus`;
}

massDeleteScope?.addEventListener('change', updateMassDeleteTarget);
massDeleteTargets.forEach((target) => target.querySelector('select').addEventListener('change', updateMassDeleteTarget));
updateMassDeleteTarget();

massDeleteForm?.addEventListener('submit', (event) => {
    const selectedOption = massDeleteTargets
        ? Array.from(massDeleteTargets).find((target) => !target.hidden)?.querySelector('select')?.selectedOptions[0]
        : null;
    const label = selectedOption?.textContent.trim() || 'kelompok ini';
    const count = Number(selectedOption?.dataset.count) || 0;
    if (count === 0 || !window.confirm(`Hapus permanen ${count} siswa dari ${label}, seluruh riwayat absensinya, dan foto mereka? Tindakan ini tidak dapat dibatalkan.`)) {
        event.preventDefault();
    }
});

studentList?.addEventListener('click', async (event) => {
    const link = event.target.closest('[data-student-qr-link]');
    if (!link || !studentQrDialog || !studentQrDialogContent) return;

    event.preventDefault();
    studentQrDialog.showModal();
    studentQrDialogContent.textContent = 'Memuat QR siswa...';

    try {
        const response = await fetch(link.href, {headers: {'Accept': 'text/html'}});
        if (!response.ok) throw new Error('QR siswa gagal dimuat.');

        const documentContent = new DOMParser().parseFromString(await response.text(), 'text/html');
        const qrPanel = documentContent.querySelector('.qr-student-panel');
        if (!qrPanel) throw new Error('QR siswa gagal dimuat.');

        studentQrDialogContent.replaceChildren(qrPanel);
    } catch (error) {
        const message = document.createElement('p');
        message.textContent = error.message || 'QR siswa gagal dimuat.';
        const fallbackLink = document.createElement('a');
        fallbackLink.className = 'button';
        fallbackLink.href = link.href;
        fallbackLink.textContent = 'Buka halaman QR';
        studentQrDialogContent.replaceChildren(message, fallbackLink);
    }
});

studentQrDialog?.querySelector('[data-student-qr-close]')?.addEventListener('click', () => studentQrDialog.close());
studentQrDialog?.addEventListener('click', (event) => {
    if (event.target === studentQrDialog) studentQrDialog.close();
});
</script>
@endpush