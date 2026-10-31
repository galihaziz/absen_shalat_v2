@extends('layouts.app')

@section('title', 'Import Siswa · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">MUTAKHIRKAN DATA</p><h1>Import siswa</h1></div></div>
<section class="panel narrow-panel">
    <p>Unggah workbook hingga 10 MB. Sistem akan membaca sheet dan kolom siswa, lalu menampilkan pratinjau. Data belum disimpan sebelum Anda memilih baris dan menekan konfirmasi.</p>
    <form class="stack-form" method="post" enctype="multipart/form-data" action="{{ route('students.import.store') }}"
        data-import-upload-form
        data-progress-url="{{ route('students.import.progress', ['progressToken' => '__PROGRESS_TOKEN__']) }}">
        @csrf
        <input type="hidden" name="progress_token" value="{{ $progressToken ?? '' }}">
        <label>File workbook<input type="file" name="excel" accept=".xlsx,.xls,.ods" required></label>
        @error('excel')<p class="warning-text">{{ $message }}</p>@enderror
        @error('preview_token')<p class="warning-text">{{ $message }}</p>@enderror
        <button class="button primary" type="submit" data-import-upload-submit>Tampilkan pratinjau</button>
        <section class="import-upload-progress" data-import-upload-progress hidden role="status" aria-live="polite" aria-busy="false">
            <div class="import-upload-progress-heading">
                <strong data-import-upload-title>Mengirim workbook</strong>
                <span data-import-upload-percent>0%</span>
            </div>
            <progress max="100" value="0" data-import-upload-meter aria-label="Progres upload workbook"></progress>
            <p data-import-upload-detail>Menunggu file diunggah.</p>
            <p class="import-server-processing" data-import-server-processing hidden>
                File sudah terkirim. Server sedang membaca sheet, mengenali kolom, memeriksa data dan duplikasi, lalu menyiapkan pratinjau.
            </p>
            <p class="import-upload-error" data-import-upload-error role="alert" hidden></p>
        </section>
    </form>
</section>

@if($errors->has('sync_alumni'))
    <p class="warning-text" role="alert">{{ $errors->first('sync_alumni') }}</p>
@endif

@isset($previewRows)
    <section class="panel import-preview-panel">
        <noscript><p class="warning-text" role="alert">Aktifkan JavaScript di browser untuk mencari, memfilter, dan memilih baris pratinjau.</p></noscript>
        <div class="import-preview-heading">
            <div>
                <p class="eyebrow">LANGKAH 2 DARI 2</p>
                <h2>Periksa sebelum menyimpan</h2>
                <p class="import-preview-description">Pilih siswa valid yang akan diimpor. Pilihan tetap tersimpan saat Anda mengganti filter atau halaman.</p>
            </div>
            <div class="import-preview-counts" aria-label="Ringkasan pratinjau">
                <span class="import-count-valid"><strong>{{ number_format($validCount) }}</strong> siap diimpor</span>
                <span class="import-count-invalid"><strong>{{ number_format($invalidCount) }}</strong> perlu diperiksa</span>
            </div>
        </div>
        <div class="import-filter-panel" data-import-filters>
            <label class="import-search-field">Cari data
                <input type="search" placeholder="Nama, nomor induk, kelas, atau masalah..." autocomplete="off" data-import-filter-search>
            </label>
            <label>Filter kelas
                <select data-import-filter-class>
                    <option value="">Semua kelas</option>
                    @foreach(collect($previewRows)->pluck('kelas')->filter()->unique()->sort() as $className)
                        <option value="{{ strtolower(trim($className)) }}">{{ $className }}</option>
                    @endforeach
                </select>
            </label>
            <label>Angkatan
                <select data-import-filter-grade>
                    <option value="">Semua angkatan</option>
                    <option value="X">Angkatan X</option>
                    <option value="XI">Angkatan XI</option>
                    <option value="XII">Angkatan XII</option>
                </select>
            </label>
            <label>Status baris
                <select data-import-filter-status>
                    <option value="">Semua status</option>
                    <option value="valid">Siap diimpor</option>
                    <option value="invalid">Perlu diperiksa</option>
                </select>
            </label>
            <label>Awalan induk
                <input type="search" inputmode="numeric" maxlength="3" placeholder="3 digit" data-import-filter-prefix>
            </label>
            <button class="button small" type="button" data-import-reset>Hapus filter</button>
            <p class="import-filter-feedback" role="status" aria-live="polite" data-import-filter-count></p>
        </div>
        @if($validCount > 0)
            <form method="post" action="{{ route('students.import.confirm') }}" id="studentImportConfirmForm">
                @csrf
                <input type="hidden" name="preview_token" value="{{ $previewToken }}">
                <input type="hidden" name="selected_rows_json" value="[]" data-import-selected-json>
                <div class="import-selection-toolbar">
                    <label class="check-label"><input type="checkbox" data-import-select-valid-all> Pilih semua siswa valid ({{ number_format($validCount) }})</label>
                    <label class="check-label"><input type="checkbox" data-import-select-page> Pilih semua baris valid di halaman ini</label>
                    <span data-import-selected-count>0 dipilih</span>
                    <button class="button small" type="button" data-import-clear>Batalkan pilihan</button>
                </div>
                <div class="table-wrap import-preview-table-wrap">
                    <table class="import-preview-table">
                        <thead><tr><th>Pilih</th><th>No.</th><th>Sheet / Baris</th><th>Nomor induk</th><th>Nama</th><th>L/P</th><th>Kelas</th><th>Status</th></tr></thead>
                        <tbody data-import-preview-body></tbody>
                    </table>
                </div>
                <nav class="import-pagination" aria-label="Halaman pratinjau import">
                    <label>Tampilkan
                        <select data-import-page-size aria-label="Jumlah baris per halaman">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                        </select>
                        baris
                    </label>
                    <span data-import-page-label></span>
                    <div>
                        <button class="button small" type="button" data-import-previous>‹ Sebelumnya</button>
                        <button class="button small" type="button" data-import-next>Berikutnya ›</button>
                    </div>
                </nav>
                <p class="warning-text">Hanya baris yang dicentang dan valid yang diimpor. Baris yang tidak dipilih tidak mengubah data siswa.</p>
                <label class="check-label import-sync-option">
                    <input type="checkbox" name="sync_alumni" value="1" data-import-sync @disabled($invalidCount > 0)>
                    Sinkronkan alumni hanya di kelas yang ada pada workbook ini
                </label>
                <p class="warning-text">Jika dipilih, siswa aktif yang tidak tercantum dalam workbook akan ditandai alumni hanya pada kelas yang terdeteksi di workbook. Opsi tersedia setelah semua baris valid dipilih dan tidak ada baris bermasalah.</p>
                <p class="import-submit-feedback" role="alert" aria-live="assertive" data-import-submit-feedback></p>
                <button class="button primary" type="submit" data-import-submit>Impor <span data-import-submit-count>0</span> siswa terpilih</button>
            </form>
        @else
            <p class="warning-text">Tidak ada baris valid yang dapat diimpor. Periksa nomor induk duplikat, judul kolom, jenis kelamin, dan kelas pada sheet.</p>
            <div class="table-wrap import-preview-table-wrap">
                <table class="import-preview-table">
                    <thead><tr><th>No.</th><th>Sheet / Baris</th><th>Nomor induk</th><th>Nama</th><th>L/P</th><th>Kelas</th><th>Masalah</th></tr></thead>
                    <tbody data-import-preview-body></tbody>
                </table>
            </div>
            <nav class="import-pagination" aria-label="Halaman pratinjau import">
                <label>Tampilkan
                    <select data-import-page-size aria-label="Jumlah baris per halaman">
                        <option value="25">25</option><option value="50" selected>50</option><option value="100">100</option>
                    </select>
                    baris
                </label>
                <span data-import-page-label></span>
                <div><button class="button small" type="button" data-import-previous>‹ Sebelumnya</button><button class="button small" type="button" data-import-next>Berikutnya ›</button></div>
            </nav>
        @endif
    </section>
@endisset
@endsection

@push('scripts')
<script src="{{ asset('js/student-import-progress.js') }}?v={{ md5_file(public_path('js/student-import-progress.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const previewData = {{ Illuminate\Support\Js::from($previewRows ?? []) }};
        const validCount = {{ $validCount ?? 0 }};
        const invalidCount = {{ $invalidCount ?? 0 }};
        const tableBody = document.querySelector('[data-import-preview-body]');
        const selectAllValid = document.querySelector('[data-import-select-valid-all]');
        const selectPage = document.querySelector('[data-import-select-page]');
        const syncAlumni = document.querySelector('[data-import-sync]');
        const classFilter = document.querySelector('[data-import-filter-class]');
        const gradeFilter = document.querySelector('[data-import-filter-grade]');
        const statusFilter = document.querySelector('[data-import-filter-status]');
        const prefixFilter = document.querySelector('[data-import-filter-prefix]');
        const searchFilter = document.querySelector('[data-import-filter-search]');
        const filterCount = document.querySelector('[data-import-filter-count]');
        const pageSizeInput = document.querySelector('[data-import-page-size]');
        const pageLabel = document.querySelector('[data-import-page-label]');
        const previousButton = document.querySelector('[data-import-previous]');
        const nextButton = document.querySelector('[data-import-next]');
        const selectedCount = document.querySelector('[data-import-selected-count]');
        const submitCount = document.querySelector('[data-import-submit-count]');
        const submitFeedback = document.querySelector('[data-import-submit-feedback]');
        const syncForm = document.querySelector('#studentImportConfirmForm');
        const selectedIds = new Set();
        let matchedRows = [...previewData.keys()];
        let currentPage = 0;

        const normalize = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('id').trim();
        const makeCell = (row, text, className = '') => {
            const cell = document.createElement('td');
            if (className) cell.className = className;
            cell.textContent = text || '—';
            row.append(cell);
            return cell;
        };

        const updateSelectionControls = (pageRows) => {
            const selectableRows = pageRows.filter((row) => row.record_id !== null);
            const selectedOnPage = selectableRows.filter((row) => selectedIds.has(row.record_id)).length;
            if (selectPage) {
                selectPage.checked = selectableRows.length > 0 && selectedOnPage === selectableRows.length;
                selectPage.indeterminate = selectedOnPage > 0 && selectedOnPage < selectableRows.length;
                selectPage.disabled = selectableRows.length === 0;
            }
            if (selectAllValid) {
                selectAllValid.checked = selectedIds.size === validCount && validCount > 0;
                selectAllValid.indeterminate = selectedIds.size > 0 && selectedIds.size < validCount;
                selectAllValid.disabled = validCount === 0;
            }
            if (selectedCount) selectedCount.textContent = `${selectedIds.size.toLocaleString('id')} dipilih`;
            if (submitCount) submitCount.textContent = selectedIds.size.toLocaleString('id');
            if (syncAlumni) {
                syncAlumni.disabled = invalidCount > 0 || selectedIds.size !== validCount;
                if (syncAlumni.disabled) syncAlumni.checked = false;
            }
        };

        const renderPage = () => {
            if (!tableBody) return;
            const pageSize = Number(pageSizeInput?.value ?? 50);
            const pageCount = Math.max(1, Math.ceil(matchedRows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount - 1);
            const start = currentPage * pageSize;
            const pageRows = matchedRows.slice(start, start + pageSize).map((index) => previewData[index]);
            tableBody.replaceChildren();

            pageRows.forEach((item, pageIndex) => {
                const row = document.createElement('tr');
                row.className = item.errors.length ? 'import-preview-invalid' : 'import-preview-valid';
                if (validCount > 0) {
                    const selectCell = document.createElement('td');
                    if (item.record_id !== null) {
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.checked = selectedIds.has(item.record_id);
                        checkbox.setAttribute('aria-label', `Pilih ${item.nama}`);
                        checkbox.addEventListener('change', () => {
                            if (checkbox.checked) selectedIds.add(item.record_id);
                            else selectedIds.delete(item.record_id);
                            updateSelectionControls(pageRows);
                        });
                        selectCell.append(checkbox);
                    }
                    row.append(selectCell);
                }
                makeCell(row, (start + pageIndex + 1).toLocaleString('id'));
                makeCell(row, `${item.sheet} · ${item.row}`);
                makeCell(row, item.induk);
                makeCell(row, item.nama);
                makeCell(row, item.jenis_kelamin);
                makeCell(row, item.kelas);
                const status = item.errors.length ? item.errors.join(' ') : 'Siap diimpor';
                const statusCell = makeCell(row, status, item.errors.length ? 'import-status-error' : 'import-status-ready');
                statusCell.setAttribute('role', item.errors.length ? 'alert' : 'status');
                tableBody.append(row);
            });

            if (pageRows.length === 0) {
                const row = document.createElement('tr');
                const cell = makeCell(row, 'Tidak ada data yang cocok. Coba ubah kata pencarian atau filter.');
                cell.colSpan = validCount > 0 ? 8 : 7;
                cell.className = 'import-empty-state';
                tableBody.append(row);
            }

            const from = matchedRows.length ? start + 1 : 0;
            const to = Math.min(start + pageSize, matchedRows.length);
            if (pageLabel) pageLabel.textContent = `${from.toLocaleString('id')}–${to.toLocaleString('id')} dari ${matchedRows.length.toLocaleString('id')} baris`;
            if (previousButton) previousButton.disabled = currentPage === 0;
            if (nextButton) nextButton.disabled = currentPage >= pageCount - 1;
            if (filterCount) filterCount.textContent = `${matchedRows.length.toLocaleString('id')} dari ${previewData.length.toLocaleString('id')} baris cocok dengan filter.`;
            updateSelectionControls(pageRows);
        };

        const applyFilters = () => {
            const selectedClass = normalize(classFilter?.value ?? '');
            const selectedGrade = gradeFilter?.value ?? '';
            const selectedStatus = statusFilter?.value ?? '';
            const prefix = (prefixFilter?.value ?? '').replace(/\D/g, '').slice(0, 3);
            const terms = normalize(searchFilter?.value).split(/\s+/).filter(Boolean);
            matchedRows = previewData.map((item, index) => ({item, index}))
                .filter(({item}) => {
                    const searchText = normalize([item.sheet, item.row, item.induk, item.nama, item.jenis_kelamin, item.kelas, ...item.errors].join(' '));
                    return (!selectedClass || normalize(item.kelas) === selectedClass)
                        && (!selectedGrade || item.grade === selectedGrade)
                        && (!selectedStatus || (selectedStatus === 'invalid' ? item.errors.length > 0 : item.errors.length === 0))
                        && (!prefix || String(item.induk_prefix).startsWith(prefix))
                        && terms.every((term) => searchText.includes(term));
                })
                .map(({index}) => index);
            currentPage = 0;
            renderPage();
        };

        selectAllValid?.addEventListener('change', () => {
            if (selectAllValid.checked) {
                previewData.forEach((item) => {
                    if (item.record_id !== null) selectedIds.add(item.record_id);
                });
            } else {
                selectedIds.clear();
            }
            renderPage();
        });
        selectPage?.addEventListener('change', () => {
            const pageSize = Number(pageSizeInput?.value ?? 50);
            const pageRows = matchedRows.slice(currentPage * pageSize, (currentPage + 1) * pageSize)
                .map((index) => previewData[index])
                .filter((item) => item.record_id !== null);
            pageRows.forEach((item) => {
                if (selectPage.checked) selectedIds.add(item.record_id);
                else selectedIds.delete(item.record_id);
            });
            renderPage();
        });
        previousButton?.addEventListener('click', () => { currentPage--; renderPage(); });
        nextButton?.addEventListener('click', () => { currentPage++; renderPage(); });
        pageSizeInput?.addEventListener('change', () => { currentPage = 0; renderPage(); });
        document.querySelector('[data-import-clear]')?.addEventListener('click', () => {
            selectedIds.clear();
            renderPage();
        });
        document.querySelector('[data-import-reset]')?.addEventListener('click', () => {
            if (classFilter) classFilter.value = '';
            if (gradeFilter) gradeFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (prefixFilter) prefixFilter.value = '';
            if (searchFilter) searchFilter.value = '';
            applyFilters();
            searchFilter?.focus();
        });
        [classFilter, gradeFilter, statusFilter].forEach((filter) => filter?.addEventListener('change', applyFilters));
        prefixFilter?.addEventListener('input', applyFilters);
        let searchTimer;
        searchFilter?.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(applyFilters, 100);
        });
        syncForm?.addEventListener('submit', (event) => {
            if (selectedIds.size === 0) {
                event.preventDefault();
                if (submitFeedback) submitFeedback.textContent = 'Pilih minimal satu baris valid sebelum melanjutkan.';
            }
            if (syncAlumni?.checked && (selectedIds.size !== validCount || invalidCount > 0)) {
                event.preventDefault();
                if (submitFeedback) submitFeedback.textContent = 'Untuk sinkronisasi alumni, pilih semua baris valid dan pastikan tidak ada baris bermasalah.';
                return;
            }
            if (selectedIds.size === 0) return;

            const selectedRowsInput = syncForm.querySelector('[data-import-selected-json]');
            if (selectedRowsInput) selectedRowsInput.value = JSON.stringify([...selectedIds]);
        });
        applyFilters();
    });
</script>
@endpush
