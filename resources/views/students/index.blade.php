@extends('layouts.app')

@section('title', 'Data Siswa · Rekap Absensi Solat')

@section('content')
<div class="page-heading">
    <div><p class="eyebrow">DATA REFERENSI</p><h1>Siswa dan rombel</h1></div>
    @unless($editing)
        <button class="button primary" type="button" data-student-create-button aria-controls="studentCreate">+ Tambah siswa</button>
    @endunless
</div>
<div class="content-grid student-crud-layout">
    <div class="student-management-column">
    <section class="panel management-panel"><details id="studentCreate" class="management-disclosure" @if($editing || $errors->any()) open @endif>
        <summary>{{ $editing ? 'Ubah data siswa' : 'Tambah siswa' }}@unless($editing)<small>Formulir siswa baru</small>@endunless</summary>
        <form class="stack-form" method="post" action="{{ route('students.store') }}" enctype="multipart/form-data" data-student-photo-compress>@csrf
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
            @if($editing)
                <label>Status siswa
                    <select name="is_alumni">
                        <option value="0" @selected((string) old('is_alumni', (int) $editing->is_alumni) === '0')>Aktif</option>
                        <option value="1" @selected((string) old('is_alumni', (int) $editing->is_alumni) === '1')>Alumni</option>
                    </select>
                </label>
            @endif
            @if($editing && $editingPhotoAvailable)
                <div class="student-photo-edit"><img class="student-edit-photo" src="{{ route('students.photo', $editing) }}" alt="Foto {{ $editing->nama }}"><label class="check-label"><input type="checkbox" name="hapus_foto" value="1" @checked(old('hapus_foto'))> Hapus foto saat disimpan</label></div>
            @elseif($editing)
                <p class="delete-feedback">Foto siswa belum tersedia.</p>
            @endif
            <label>Foto siswa (opsional)<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></label>
            <p class="filter-feedback" data-student-photo-upload-status role="status" aria-live="polite"></p>
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
    <section class="panel management-panel major-label-panel"><details class="management-disclosure">
        <summary>Kelola jurusan <small>{{ count($majorGroups) }} grup</small></summary>
        <p class="filter-feedback">Setiap grup memiliki nama tampilan dan pilihan jurusan terkait. Nama grup akan dipakai pada filter, cetak kartu, dan ringkasan.</p>
        @error('major_group')<p class="filter-feedback" role="alert">{{ $message }}</p>@enderror
        <div class="major-group-list">
            @foreach($majorGroups as $majorGroup)
                <div class="major-group-item">
                    <form class="major-group-edit" method="post" action="{{ route('students.majors.update', $majorGroup['id']) }}" data-major-group-form>
                        @csrf @method('PUT')
                        <input type="hidden" name="group_id" value="{{ $majorGroup['id'] }}">
                        @php($selectedMajors = old('group_id') === $majorGroup['id'] ? old('majors', $majorGroup['majors']) : $majorGroup['majors'])
                        <label>Nama jurusan
                            <input name="name" value="{{ old('group_id') === $majorGroup['id'] ? old('name', $majorGroup['name']) : $majorGroup['name'] }}" maxlength="50" required>
                        </label>
                        <div class="major-select-list" data-major-select-list>
                            @foreach(range(0, max(0, count($selectedMajors) - 1)) as $majorIndex)
                                <div class="major-select-item">
                                    <label>Jurusan terkait <span data-major-index>{{ $majorIndex + 1 }}</span>
                                        <select name="majors[]" @required($majorIndex === 0) data-major-select>
                                            <option value="">Pilih jurusan</option>
                                            @foreach($availableRelatedMajors as $relatedMajor)
                                                <option value="{{ $relatedMajor }}" @selected(($selectedMajors[$majorIndex] ?? '') === $relatedMajor)>{{ $relatedMajor }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    @if($majorIndex > 0)<button class="button quiet small" type="button" data-major-remove aria-label="Hapus pilihan jurusan terkait">Hapus</button>@endif
                                </div>
                            @endforeach
                        </div>
                        <button class="button quiet small major-select-add" type="button" data-major-add>+ Tambah jurusan terkait</button>
                        <p class="major-select-preview" data-major-preview aria-live="polite">{{ implode(' - ', array_filter($selectedMajors)) }}</p>
                        <template data-major-select-template>
                            <div class="major-select-item">
                                <label>Jurusan terkait <span data-major-index></span>
                                    <select name="majors[]" data-major-select>
                                        <option value="">Pilih jurusan</option>
                                        @foreach($availableRelatedMajors as $relatedMajor)
                                            <option value="{{ $relatedMajor }}">{{ $relatedMajor }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button class="button quiet small" type="button" data-major-remove aria-label="Hapus pilihan jurusan terkait">Hapus</button>
                            </div>
                        </template>
                        <button class="button primary small" type="submit">Simpan</button>
                    </form>
                    <form method="post" action="{{ route('students.majors.destroy', $majorGroup['id']) }}" onsubmit="return confirm('Hapus grup jurusan {{ $majorGroup['name'] }}?')">
                        @csrf @method('DELETE')
                        <button class="button danger small" type="submit">Hapus</button>
                    </form>
                </div>
            @endforeach
        </div>
        <form class="major-group-create" method="post" action="{{ route('students.majors.store') }}" data-major-group-form>
            @csrf
            <h3>Tambah grup jurusan</h3>
            <label>Nama jurusan<input name="name" value="{{ old('group_id') ? '' : old('name') }}" maxlength="50" placeholder="Contoh: Rekayasa Perangkat Lunak" required></label>
            <div class="major-select-list" data-major-select-list>
                <div class="major-select-item">
                    <label>Jurusan terkait <span data-major-index>1</span>
                        <select name="majors[]" required data-major-select>
                            <option value="">Pilih jurusan</option>
                            @foreach($availableRelatedMajors as $relatedMajor)
                                <option value="{{ $relatedMajor }}" @selected(! old('group_id') && (old('majors.0') ?? '') === $relatedMajor)>{{ $relatedMajor }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @if(! old('group_id'))
                    @foreach(array_slice(old('majors', []), 1) as $oldMajor)
                        <div class="major-select-item">
                            <label>Jurusan terkait <span data-major-index>{{ $loop->iteration + 1 }}</span>
                                <select name="majors[]" data-major-select>
                                    <option value="">Pilih jurusan</option>
                                    @foreach($availableRelatedMajors as $relatedMajor)
                                        <option value="{{ $relatedMajor }}" @selected($oldMajor === $relatedMajor)>{{ $relatedMajor }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button class="button quiet small" type="button" data-major-remove aria-label="Hapus pilihan jurusan terkait">Hapus</button>
                        </div>
                    @endforeach
                @endif
            </div>
            <button class="button quiet small major-select-add" type="button" data-major-add>+ Tambah jurusan terkait</button>
            <p class="major-select-preview" data-major-preview aria-live="polite">{{ implode(' - ', old('group_id') ? [] : array_filter(old('majors', []))) }}</p>
            <template data-major-select-template>
                <div class="major-select-item">
                    <label>Jurusan terkait <span data-major-index></span>
                        <select name="majors[]" data-major-select>
                            <option value="">Pilih jurusan</option>
                            @foreach($availableRelatedMajors as $relatedMajor)
                                <option value="{{ $relatedMajor }}">{{ $relatedMajor }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="button quiet small" type="button" data-major-remove aria-label="Hapus pilihan jurusan terkait">Hapus</button>
                </div>
            </template>
            <button class="button primary" type="submit">Tambah grup</button>
        </form>
    </details></section>
    <section class="panel compact-panel student-photo-panel">
        <div class="student-photo-panel-heading">
            <p class="eyebrow">FOTO PROFIL</p>
            <h2>Unggah foto siswa</h2>
            <p>Tambahkan foto satu siswa atau unggah beberapa foto sekaligus. Foto otomatis diperkecil sebelum disimpan.</p>
        </div>
        <div class="student-photo-single">
            <div>
                <h3>Unggah satu foto</h3>
                <p>Untuk mengganti atau menambahkan foto seorang siswa.</p>
            </div>
            <form class="filters student-photo-single-form" method="post" action="{{ route('students.photo.store') }}" enctype="multipart/form-data" data-student-photo-compress>
                @csrf
                <label>Nomor induk<input name="induk" inputmode="numeric" pattern="[0-9]+" maxlength="30" placeholder="Contoh: 123456" required></label>
                <label>File foto<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required></label>
                <button class="button primary" type="submit">Simpan foto</button>
                <p class="filter-feedback" data-student-photo-upload-status role="status" aria-live="polite"></p>
            </form>
        </div>

        <div class="student-photo-bulk">
            <div class="student-photo-bulk-heading">
                <div>
                    <h3>Unggah banyak foto</h3>
                    <p>Pilih kelas atau jurusan per angkatan, lalu unggah foto yang nama filenya berisi nomor induk atau nama siswa.</p>
                </div>
                <span class="student-photo-limit">Maks. 100 foto</span>
            </div>
            <ol class="student-photo-steps">
                <li><strong>Atur pencocokan</strong><span>Tentukan apakah nama file berisi nomor induk atau nama.</span></li>
                <li><strong>Pilih kelompok</strong><span>Batasi pencarian ke kelas atau jurusan dan angkatan.</span></li>
                <li><strong>Pilih foto</strong><span>Pastikan tiap foto memiliki nama file yang sesuai.</span></li>
            </ol>
            <form class="student-photo-bulk-form" method="post" action="{{ route('students.photo.mass') }}" enctype="multipart/form-data" data-student-photo-bulk>
                @csrf
                <div class="student-photo-options">
                    <label>Cocokkan nama file dengan
                        <select name="match_by" data-photo-match-by>
                            <option value="induk">Nomor induk</option>
                            <option value="nama">Nama siswa</option>
                        </select>
                    </label>
                    <label>Kelompok siswa
                        <select name="photo_scope" data-photo-bulk-scope>
                            <option value="class">Satu kelas</option>
                            <option value="major_grade">Satu jurusan per angkatan</option>
                        </select>
                    </label>
                    <label data-photo-bulk-target="class">Kelas
                        <select name="photo_classroom_id">
                            @foreach($classes as $class)<option value="{{ $class->id }}" @selected((string) $photoDefaultClassroomId === (string) $class->id)>{{ $class->nama }}</option>@endforeach
                        </select>
                    </label>
                    <label data-photo-bulk-target="major_grade" hidden>Angkatan
                        <select name="photo_grade" disabled>
                            @foreach($grades as $grade)<option value="{{ $grade }}">Kelas {{ $grade }}</option>@endforeach
                        </select>
                    </label>
                    <label data-photo-bulk-target="major_grade" hidden>Jurusan
                        <select name="photo_major" disabled>
                            @foreach($majors as $major)<option value="{{ $major }}">{{ $major }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <p class="student-photo-match-help" data-photo-match-help>Contoh nama file: <strong>123456.jpg</strong>. Pastikan nomor induk pada nama file sesuai dengan data siswa.</p>
                <label class="student-photo-dropzone">
                    <input type="file" name="fotos[]" accept=".jpg,.jpeg,.png,.webp" multiple required data-student-photo-files aria-describedby="studentPhotoFileHint">
                    <span class="student-photo-dropzone-icon" aria-hidden="true">↑</span>
                    <span class="student-photo-dropzone-title">Pilih foto dari perangkat</span>
                    <span class="student-photo-dropzone-hint" id="studentPhotoFileHint">Klik atau seret foto ke area ini · JPG, PNG, atau WebP</span>
                </label>
                <div class="student-photo-file-summary" data-student-photo-file-summary aria-live="polite">Belum ada foto dipilih.</div>
                <ul class="student-photo-file-list" data-student-photo-file-list hidden></ul>
                <div class="student-photo-upload-progress" data-student-photo-progress-wrap hidden>
                    <progress max="100" value="0" data-student-photo-progress aria-label="Progres unggah foto"></progress>
                </div>
                <p class="student-photo-compression-note">Foto dikompres otomatis di bawah 100 KiB dan diunggah satu per satu agar lebih stabil, termasuk saat koneksi hosting lambat.</p>
                <button class="button primary student-photo-submit" type="submit" data-student-photo-submit disabled>Pilih foto untuk mulai</button>
                <p class="student-photo-upload-status" data-student-photo-bulk-status role="status" aria-live="polite"></p>
            </form>
        </div>
    </section>
    </div>
    <section class="panel student-list-panel" data-student-list data-next-page="{{ $students->nextPageUrl() }}">
    @unless($showingAlumni)
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
    @endunless
    <div class="section-heading">
        <div>
            <h2>{{ $showingAlumni ? 'Daftar siswa alumni' : 'Daftar siswa aktif' }} <small data-student-count data-count="{{ $students->count() }}">{{ $students->count() }} ditampilkan</small></h2>
            <div class="actions" role="group" aria-label="Filter status siswa">
                <a class="button {{ $showingAlumni ? 'quiet' : 'primary' }}" href="{{ route('students.index', ['status' => 'aktif', 'cari' => $search ?: null]) }}">Siswa aktif ({{ $activeStudentCount }})</a>
                <a class="button {{ $showingAlumni ? 'primary' : 'quiet' }}" href="{{ route('students.index', ['status' => 'alumni', 'cari' => $search ?: null]) }}">Siswa alumni ({{ $alumniStudentCount }})</a>
            </div>
        </div>
        <div class="actions">
            @unless($showingAlumni)<a class="button quiet" href="{{ route('students.qr.selection') }}">Pilih siswa manual</a>@endunless
            <form class="search-form" method="get" data-student-search>
                <input type="hidden" name="status" value="{{ $showingAlumni ? 'alumni' : 'aktif' }}">
                <input name="cari" value="{{ $search }}" aria-label="Cari siswa berdasarkan nama, nomor induk, atau kelas" placeholder="Nama, nomor induk, atau kelas">
                <button class="button" type="submit">Cari</button>
                @if($search !== '')<a class="button quiet filter-reset" href="{{ route('students.index', ['status' => $showingAlumni ? 'alumni' : 'aktif']) }}">Reset</a>@endif
            </form>
        </div>
    </div>
    @if($search !== '')<p class="filter-feedback" role="status">Pencarian aktif: “{{ $search }}”. {{ $matchingStudentCount }} siswa cocok.</p>@endif
    @if($showingAlumni)
        <form id="bulkAlumniForm" method="post" action="{{ route('students.alumni.bulk-action') }}" data-bulk-alumni-form>
            @csrf
            <div class="bulk-student-actions">
                <label class="check-label"><input type="checkbox" data-alumni-select-all> Pilih semua yang ditampilkan</label>
                <span data-alumni-selection-count role="status" aria-live="polite">0 siswa dipilih</span>
                <button class="button danger" type="submit" name="action" value="delete" data-alumni-action disabled>Hapus yang dipilih</button>
                <button class="button primary" type="submit" name="action" value="activate" data-alumni-action disabled>Jadikan siswa aktif</button>
            </div>
        </form>
        @error('student_ids')<p class="filter-feedback" role="alert">{{ $message }}</p>@enderror
    @endif
    <div class="table-wrap" data-student-table-wrap><table><thead><tr>@if($showingAlumni)<th><span class="visually-hidden">Pilih</span></th>@endif<th class="table-row-number">No.</th><th>Induk</th><th class="sticky-name">Nama</th><th>L/P</th><th>Kelas</th><th>Kategori</th><th>Aksi</th></tr></thead><tbody data-student-table-body>
    @include('students._rows', ['students' => $students, 'showingAlumni' => $showingAlumni])
    </tbody></table><div class="student-load-trigger" data-student-load-trigger @if(! $students->hasMorePages()) hidden @endif><span class="loading-feedback" data-student-load-status role="status" aria-live="polite"></span><button class="button quiet small" type="button" data-student-load-button>Muat data berikutnya</button></div></div>
</section>
</div>
<section class="panel bulk-student-delete-panel">
    <div class="section-heading">
        <div><p class="eyebrow">TINDAKAN PERMANEN</p><h2>Hapus data siswa massal</h2><p>Hapus siswa aktif maupun alumni berdasarkan kelas, tingkat, atau jurusan. Pilihan khusus alumni menghapus semua alumni.</p></div>
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
            <option value="alumni" @selected(old('scope') === 'alumni')>Semua siswa alumni</option>
            </select>
        </label>
        @foreach(['class' => 'Kelas', 'grade' => 'Tingkat', 'major' => 'Jurusan', 'alumni' => 'Alumni'] as $deleteScope => $deleteLabel)
            <label data-mass-delete-target="{{ $deleteScope }}" @if($deleteScope !== 'class') hidden @endif>
                {{ $deleteLabel }}
                <select name="{{ ['class' => 'classroom_id', 'grade' => 'grade', 'major' => 'major', 'alumni' => 'alumni'][$deleteScope] }}" @disabled($deleteScope !== 'class')>
                    @foreach($massDeleteOptions[$deleteScope] as $option)
                        <option value="{{ $option['value'] }}" data-count="{{ $option['count'] }}" @selected((string) old(['class' => 'classroom_id', 'grade' => 'grade', 'major' => 'major', 'alumni' => 'alumni'][$deleteScope]) === (string) $option['value'])>{{ $option['label'] }} · {{ $option['count'] }} siswa</option>
                    @endforeach
                </select>
            </label>
        @endforeach
        <input type="hidden" name="expected_count" data-mass-delete-expected>
        <button class="button danger" type="submit" @disabled(collect($massDeleteOptions)->every(fn ($options) => $options->isEmpty()))>Hapus kelompok terpilih</button>
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
<script src="{{ asset('js/student-photo-bulk.js') }}?v={{ md5_file(public_path('js/student-photo-bulk.js')) }}" defer></script>
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

document.querySelectorAll('form[data-major-group-form]').forEach((form) => {
    const list = form.querySelector('[data-major-select-list]');
    const template = form.querySelector('[data-major-select-template]');
    const addButton = form.querySelector('[data-major-add]');
    const preview = form.querySelector('[data-major-preview]');
    if (!list || !template || !addButton || !preview) return;

    const refreshMajorSelections = () => {
        const selects = Array.from(list.querySelectorAll('[data-major-select]'));
        const selected = selects.map((select) => select.value).filter(Boolean);

        selects.forEach((select, index) => {
            select.required = index === 0;
            Array.from(select.options).forEach((option) => {
                option.disabled = Boolean(option.value && selected.includes(option.value) && select.value !== option.value);
            });
            const label = select.closest('.major-select-item');
            const labelIndex = label?.querySelector('[data-major-index]');
            if (labelIndex) labelIndex.textContent = String(index + 1);
            const removeButton = label?.querySelector('[data-major-remove]');
            if (removeButton) removeButton.hidden = selects.length === 1;
        });

        preview.textContent = selected.join(' - ');
        addButton.disabled = selects.length >= 8;
    };

    addButton.addEventListener('click', () => {
        if (list.querySelectorAll('[data-major-select]').length >= 8) return;
        const newItem = template.content.firstElementChild.cloneNode(true);
        list.append(newItem);
        refreshMajorSelections();
        newItem.querySelector('[data-major-select]')?.focus();
    });

    list.addEventListener('change', (event) => {
        if (event.target.matches('[data-major-select]')) refreshMajorSelections();
    });
    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-major-remove]');
        if (!removeButton) return;
        removeButton.closest('.major-select-item')?.remove();
        refreshMajorSelections();
    });
    form.addEventListener('submit', (event) => {
        const selections = Array.from(list.querySelectorAll('[data-major-select]'))
            .map((select) => select.value)
            .filter(Boolean);
        if (new Set(selections).size !== selections.length) {
            event.preventDefault();
            preview.textContent = 'Jurusan yang sama tidak bisa dipilih lebih dari sekali.';
        }
    });

    refreshMajorSelections();
});

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

    if (window.matchMedia('(max-width: 640px)').matches) return;

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
studentQrDialogContent?.addEventListener('click', (event) => {
    const downloadLink = event.target.closest('a[download]');
    if (downloadLink && studentQrDialog.open) studentQrDialog.close();
});
studentQrDialog?.addEventListener('click', (event) => {
    if (event.target === studentQrDialog) studentQrDialog.close();
});
</script>
@endpush