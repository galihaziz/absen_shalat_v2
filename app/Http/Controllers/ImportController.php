<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportController extends Controller
{
    public function create(): View
    {
        return view('students.import', ['progressToken' => (string) Str::uuid()]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls,ods', 'max:10240'],
            'progress_token' => ['nullable', 'uuid'],
        ], [
            'excel.required' => 'File belum diterima. Periksa upload_max_filesize, post_max_size, dan folder sementara upload pada hosting.',
            'excel.file' => 'File gagal diterima server. Periksa batas upload dan folder sementara PHP pada hosting.',
            'excel.max' => 'Workbook maksimal 10 MiB.',
        ]);
        $this->clearPreview($request);
        $progressToken = $data['progress_token'] ?? null;
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->updateProgress($progressToken, $userId, 3, 'Memvalidasi file', 'File diterima. Memeriksa format workbook.');
        $this->removeExpiredPreviews();
        $request->session()->save();

        try {
            $reader = IOFactory::createReaderForFile($data['excel']->getRealPath());
            $reader->setReadEmptyCells(false);
            $sheetNames = array_values(array_filter(
                $reader->listWorksheetNames($data['excel']->getRealPath()),
                static fn (string $sheetName): bool => preg_match('/lulus|template|link/i', $sheetName) !== 1
            ));
            if ($sheetNames === []) {
                $this->updateProgress($progressToken, $userId, 100, 'Workbook tidak dapat diproses', 'Tidak ditemukan sheet siswa yang dapat diimpor.', true);

                return back()->withInput()->withErrors([
                    'excel' => 'Workbook tidak memiliki sheet siswa yang dapat diimpor.',
                ]);
            }
            $this->updateProgress($progressToken, $userId, 10, 'Mengenali sheet', count($sheetNames).' sheet siswa ditemukan. Menyiapkan pembacaan workbook.');
            $reader->setLoadSheetsOnly($sheetNames);
            $workbook = $reader->load($data['excel']->getRealPath());
        } catch (PhpSpreadsheetException) {
            $this->updateProgress($progressToken, $userId, 100, 'Workbook tidak dapat dibaca', 'Simpan ulang file sebagai XLSX, XLS, atau ODS lalu unggah kembali.', true);

            return back()->withInput()->withErrors([
                'excel' => 'File tidak dapat dibaca sebagai workbook yang valid. Simpan ulang sebagai XLSX, XLS, atau ODS lalu unggah kembali.',
            ]);
        }

        $sheets = $workbook->getAllSheets();
        $totalRows = max(1, array_sum(array_map(
            static fn (Worksheet $sheet): int => max(1, $sheet->getHighestDataRow()),
            $sheets
        )));
        $completedSheetRows = 0;
        $this->updateProgress($progressToken, $userId, 20, 'Membaca workbook', 'Membaca baris dan mengenali kolom siswa.');
        $records = [];
        $previewRows = [];

        foreach ($sheets as $sheet) {
            $sheetName = $sheet->getTitle();
            if (preg_match('/lulus|template|link/i', $sheetName)) {
                continue;
            }
            $currentClass = $this->classFromSheetName($sheetName);
            $columns = ['nama' => null, 'induk' => null, 'jenis_kelamin' => null, 'kelas' => null];
            $rowNumber = 0;
            $sheetTotalRows = max(1, $sheet->getHighestDataRow());

            foreach ($this->rowsFromSheet($sheet) as $row) {
                $rowNumber++;
                if ($rowNumber % 50 === 0) {
                    $readingPercent = 20 + (int) floor((min($completedSheetRows + $rowNumber, $totalRows) / $totalRows) * 55);
                    $this->updateProgress(
                        $progressToken,
                        $userId,
                        min(75, $readingPercent),
                        'Membaca data siswa',
                        sprintf('Membaca sheet %s, baris %s dari sekitar %s.', $sheetName, number_format($rowNumber, 0, ',', '.'), number_format($sheetTotalRows, 0, ',', '.'))
                    );
                }
                $detected = [
                    'nama' => $this->findColumn($row, ['nama', 'name', 'namasiswa', 'namalengkap', 'namapeserta']),
                    'induk' => $this->findColumn($row, ['induk', 'nomorinduk', 'induksiswa', 'noinduk', 'noinduksiswa', 'nis', 'nisn']),
                    'jenis_kelamin' => $this->findColumn($row, ['jeniskelamin', 'jeniskelaminsiswa', 'jk', 'lp', 'gender', 'sex']),
                    'kelas' => $this->findColumn($row, ['kelas', 'rombel', 'class', 'namakelas']),
                ];

                if ($detected['nama'] === null && $detected['induk'] === null) {
                    $detected['kelas'] = null;
                }
                if ($detected['nama'] === null && $detected['induk'] === null && $detected['kelas'] === null) {
                    foreach ($row as $cell) {
                        if (preg_match('/^(XII|XI|X)(?:\s+.+)?$/i', trim((string) $cell))) {
                            $currentClass = trim((string) $cell);
                            break;
                        }
                    }
                }
                if ($detected['nama'] !== null || $detected['induk'] !== null || $detected['kelas'] !== null) {
                    if ($detected['nama'] !== null) {
                        $columns['nama'] = $detected['nama'];
                    }
                    if ($detected['induk'] !== null) {
                        $columns['induk'] = $detected['induk'];
                        if ($columns['nama'] === $columns['induk'] && isset($row[$columns['induk'] + 3])) {
                            $columns['nama'] = $columns['induk'] + 3;
                            $columns['jenis_kelamin'] = $columns['induk'] + 4;
                        }
                    }
                    $detectedGender = $this->findColumn($row, ['jeniskelamin', 'jeniskelaminsiswa', 'jk', 'lp', 'gender', 'sex']);
                    if ($detectedGender !== null) {
                        $columns['jenis_kelamin'] = $detectedGender;
                    }
                    if ($detected['kelas'] !== null) {
                        $columns['kelas'] = $detected['kelas'];
                    }

                    continue;
                }
                if ($columns['induk'] === null || $columns['nama'] === null) {
                    foreach ($row as $cell) {
                        if (preg_match('/^(XII|XI|X)(?:\s+.+)?$/i', trim((string) $cell))) {
                            $currentClass = trim((string) $cell);
                            break;
                        }
                    }

                    continue;
                }
                $rowClass = trim((string) ($row[$columns['kelas']] ?? ''));
                $className = $rowClass !== '' ? $rowClass : $currentClass;
                if ($className === '') {
                    $name = trim((string) ($row[$columns['nama']] ?? ''));
                    $rawInduk = trim((string) ($row[$columns['induk']] ?? ''));
                    $genderValue = trim((string) ($row[$columns['jenis_kelamin']] ?? ''));
                    if ($name !== '' || $rawInduk !== '' || $genderValue !== '') {
                        $previewRows[] = [
                            'sheet' => $sheetName,
                            'row' => $rowNumber,
                            'induk' => $rawInduk,
                            'induk_prefix' => $this->studentNumberPrefix($rawInduk),
                            'nama' => $name,
                            'jenis_kelamin' => $genderValue,
                            'kelas' => '',
                            'grade' => '',
                            'errors' => ['Kelas tidak ditemukan dari nama sheet atau kolom.'],
                            'record_id' => null,
                        ];
                    }
                    continue;
                }

                $name = trim((string) ($row[$columns['nama']] ?? ''));
                $rawInduk = trim((string) ($row[$columns['induk']] ?? ''));
                $induk = $this->normalizeInduk($rawInduk);
                $genderValue = trim((string) ($row[$columns['jenis_kelamin']] ?? ''));
                $gender = $this->normalizeGender($genderValue);
                if ($gender === null) {
                    foreach ($row as $cell) {
                        $gender = $this->normalizeGender((string) $cell);
                        if ($gender !== null) {
                            break;
                        }
                    }
                }
                if ($name === '' && $rawInduk === '' && $genderValue === '') {
                    continue;
                }

                $errors = [];
                if ($induk === '') {
                    $errors[] = 'Nomor induk kosong atau bukan angka.';
                }
                if ($name === '' || is_numeric($name)) {
                    $errors[] = 'Nama kosong atau tidak valid.';
                }
                if ($gender === null) {
                    $errors[] = 'Jenis kelamin tidak dikenali.';
                }

                $previewRow = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'induk' => $induk !== '' ? $induk : $rawInduk,
                    'induk_prefix' => $this->studentNumberPrefix($induk !== '' ? $induk : $rawInduk),
                    'nama' => $name,
                    'jenis_kelamin' => $gender ?? $genderValue,
                    'kelas' => $className,
                    'grade' => $this->gradeFromClassName($className),
                    'errors' => $errors,
                    'record_id' => null,
                ];
                if ($errors === []) {
                    $recordId = count($records);
                    $records[] = ['induk' => $induk, 'nama' => $name, 'jenis_kelamin' => $gender, 'kelas' => $className];
                    $previewRow['record_id'] = $recordId;
                }
                $previewRows[] = $previewRow;
            }

            $completedSheetRows += $sheetTotalRows;
            $readingPercent = 20 + (int) floor(($completedSheetRows / $totalRows) * 55);
            $this->updateProgress(
                $progressToken,
                $userId,
                min(75, $readingPercent),
                'Sheet selesai dibaca',
                'Selesai membaca sheet '.$sheetName.'.'
            );
        }
        $workbook->disconnectWorksheets();

        $this->updateProgress($progressToken, $userId, 78, 'Memeriksa data', 'Memeriksa kelengkapan identitas dan mendeteksi nomor induk duplikat.');
        $indukCounts = array_count_values(array_filter(
            array_map(fn (array $row): string => $this->normalizeInduk((string) $row['induk']), $previewRows),
            static fn (string $induk): bool => $induk !== ''
        ));
        $uniqueRecords = [];
        foreach ($previewRows as &$previewRow) {
            $induk = $this->normalizeInduk((string) $previewRow['induk']);
            $duplicateInduk = $induk !== '' && ($indukCounts[$induk] ?? 0) > 1;
            if ($duplicateInduk) {
                $previewRow['errors'][] = 'Nomor induk duplikat di workbook; semua baris dengan nomor ini perlu diperiksa.';
            }
            if ($previewRow['record_id'] === null) {
                continue;
            }

            $record = $records[$previewRow['record_id']];
            if ($duplicateInduk) {
                $previewRow['record_id'] = null;
                continue;
            }

            $previewRow['record_id'] = count($uniqueRecords);
            $uniqueRecords[] = $record;
        }
        unset($previewRow);
        $records = $uniqueRecords;

        $token = null;
        if ($records !== []) {
            $this->updateProgress($progressToken, $userId, 90, 'Menyiapkan pratinjau', 'Menyimpan data sementara pratinjau dengan aman.');
            $token = (string) Str::uuid();
            $previewPath = $this->previewPath($token);
            if (! Storage::disk('local')->put($previewPath, json_encode([
                'user_id' => (int) $request->user()->getAuthIdentifier(),
                'records' => $records,
                'invalid_count' => count(array_filter($previewRows, static fn (array $row): bool => $row['errors'] !== [])),
                'expires_at' => time() + 3600,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) {
                throw new \RuntimeException('Pratinjau import tidak dapat disimpan. Silakan coba lagi.');
            }
            $request->session()->put('student_import_preview', $token);
        }

        $this->updateProgress($progressToken, $userId, 100, 'Pratinjau siap', sprintf(
            '%s baris valid dan %s baris perlu diperiksa.',
            number_format(count($records), 0, ',', '.'),
            number_format(count(array_filter($previewRows, static fn (array $row): bool => $row['errors'] !== [])), 0, ',', '.')
        ), true);

        return view('students.import', [
            'previewRows' => $previewRows,
            'previewToken' => $token,
            'progressToken' => (string) Str::uuid(),
            'validCount' => count($records),
            'invalidCount' => count(array_filter($previewRows, static fn (array $row): bool => $row['errors'] !== [])),
        ]);
    }

    public function progress(Request $request, string $progressToken): JsonResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $progressToken) === 1, 404);

        return response()->json(Cache::get($this->progressKey(
            $progressToken,
            (int) $request->user()->getAuthIdentifier()
        )))->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preview_token' => ['required', 'uuid'],
            'selected_rows' => ['required', 'array', 'min:1'],
            'selected_rows.*' => ['required', 'integer', 'min:0'],
            'sync_alumni' => ['nullable', 'boolean'],
        ]);
        $token = (string) $data['preview_token'];
        abort_unless(hash_equals((string) $request->session()->get('student_import_preview', ''), $token), 404);

        abort_unless(Storage::disk('local')->exists($this->previewPath($token)), 404);
        $preview = json_decode((string) Storage::disk('local')->get($this->previewPath($token)), true, flags: JSON_THROW_ON_ERROR);
        abort_unless((int) ($preview['user_id'] ?? 0) === (int) $request->user()->getAuthIdentifier(), 404);
        if ((int) ($preview['expires_at'] ?? 0) < time()) {
            $this->clearPreview($request);

            return redirect()->route('students.import')->withErrors([
                'preview_token' => 'Pratinjau sudah kedaluwarsa. Unggah ulang workbook untuk melanjutkan.',
            ]);
        }

        $allRecords = $preview['records'] ?? [];
        $selectedIds = array_values(array_unique(array_map('intval', $data['selected_rows'])));
        foreach ($selectedIds as $id) {
            abort_unless(array_key_exists($id, $allRecords), 422, 'Pilihan baris import tidak valid.');
        }
        $syncAlumni = (bool) ($data['sync_alumni'] ?? false);
        if ($syncAlumni && (count($selectedIds) !== count($allRecords) || (int) ($preview['invalid_count'] ?? 0) > 0)) {
            return redirect()->route('students.import')->withErrors([
                'sync_alumni' => 'Sinkronisasi alumni hanya tersedia jika semua baris valid dipilih dan tidak ada baris bermasalah.',
            ]);
        }

        $records = array_map(static fn (int $id): array => $allRecords[$id], $selectedIds);
        $importedClassCount = count(array_unique(array_column($records, 'kelas')));
        $classNames = array_values(array_unique(array_column($records, 'kelas')));
        $classIds = Classroom::query()->whereIn('nama', $classNames)->pluck('id', 'nama')->all();
        $missingClasses = array_values(array_diff($classNames, array_keys($classIds)));
        if ($missingClasses !== []) {
            Classroom::query()->insertOrIgnore(array_map(
                static fn (string $name): array => ['nama' => $name],
                $missingClasses
            ));
            $classIds = Classroom::query()->whereIn('nama', $classNames)->pluck('id', 'nama')->all();
        }
        if (count($classIds) !== count($classNames)) {
            throw new \RuntimeException('Sebagian kelas tidak dapat disiapkan untuk import. Periksa kembali data kelas lalu coba lagi.');
        }

        $syncClassIds = array_values(array_unique(array_map(
            static fn (array $record): int => (int) $classIds[$record['kelas']],
            $records
        )));
        $result = DB::transaction(function () use ($records, $importedClassCount, $syncAlumni, $classIds, $syncClassIds): array {
            $studentsByInduk = [];
            foreach (array_chunk($records, 500) as $recordChunk) {
                $indukValues = array_values(array_unique(array_column($recordChunk, 'induk')));

                Student::query()
                    ->select(['id', 'induk'])
                    ->whereIn('induk', $indukValues)
                    ->orderBy('id')
                    ->get()
                    ->each(function (Student $student) use (&$studentsByInduk): void {
                        if ($student->induk !== null && ! array_key_exists($student->induk, $studentsByInduk)) {
                            $studentsByInduk[$student->induk] = $student->getAttributes();
                        }
                    });
            }

            $added = 0;
            $updated = 0;
            $importedInduk = [];
            $updatesById = [];
            $newStudents = [];
            $defaultPortalPinHash = null;

            foreach ($records as $record) {
                $classId = (int) $classIds[$record['kelas']];
                $student = $studentsByInduk[$record['induk']] ?? null;

                if ($student !== null) {
                    $updatesById[$student['id']] = [
                        'id' => $student['id'],
                        'nama' => $record['nama'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'id_kelas' => $classId,
                        'is_alumni' => false,
                    ];
                    $updated++;
                } else {
                    $defaultPortalPinHash ??= Hash::make((string) config('app.student_default_pin', 'siswa123'));
                    $student = [
                        'induk' => $record['induk'],
                        'nama' => $record['nama'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'id_kelas' => $classId,
                        'is_alumni' => false,
                        'portal_pin' => $defaultPortalPinHash,
                    ];
                    $newStudents[] = $student;
                    $added++;
                }

                $importedInduk[$record['induk']] = true;
            }

            foreach (array_chunk(array_values($updatesById), 100) as $chunk) {
                DB::table('siswa')->upsert($chunk, ['id'], ['nama', 'jenis_kelamin', 'id_kelas', 'is_alumni']);
            }
            foreach (array_chunk($newStudents, 100) as $chunk) {
                DB::table('siswa')->insert($chunk);
            }

            $alumni = 0;
            if ($syncAlumni) {
                $alumniQuery = DB::table('siswa')
                    ->where('is_alumni', false)
                    ->whereNotNull('induk')
                    ->whereIn('id_kelas', $syncClassIds);
                $importedIndukValues = array_keys($importedInduk);

                if (count($importedIndukValues) <= 400) {
                    $alumni = $alumniQuery
                        ->whereNotIn('induk', $importedIndukValues)
                        ->update(['is_alumni' => true]);
                } else {
                    $alumniQuery->select(['id', 'induk'])->orderBy('id')->chunkById(400, function ($students) use (&$alumni, $importedInduk): void {
                        $alumniIds = $students
                            ->reject(static fn (object $student): bool => isset($importedInduk[$student->induk]))
                            ->pluck('id')
                            ->all();
                        if ($alumniIds !== []) {
                            $alumni += DB::table('siswa')
                                ->whereIn('id', $alumniIds)
                                ->where('is_alumni', false)
                                ->update(['is_alumni' => true]);
                        }
                    });
                }
            }

            return ['classes' => $importedClassCount, 'added' => $added, 'updated' => $updated, 'alumni' => $alumni];
        });

        Storage::disk('local')->delete($this->previewPath($token));
        $request->session()->forget('student_import_preview');

        return redirect()->route('students.import')->with('status', sprintf(
            'Import selesai dari %d kelas. %d siswa baru, %d siswa diperbarui, %d siswa ditandai alumni. Riwayat absensi tetap tersimpan.',
            $result['classes'], $result['added'], $result['updated'], $result['alumni']
        ));
    }

    private function previewPath(string $token): string
    {
        return 'student-import-previews/'.$token.'.json';
    }

    private function progressKey(string $token, int $userId): string
    {
        return 'student-import-progress:'.$userId.':'.($token ?? '');
    }

    private function updateProgress(
        ?string $token,
        int $userId,
        int $percent,
        string $stage,
        string $detail,
        bool $complete = false
    ): void {
        if ($token === null) {
            return;
        }

        Cache::put($this->progressKey($token, $userId), [
            'percent' => min(100, max(0, $percent)),
            'stage' => $stage,
            'detail' => $detail,
            'complete' => $complete,
        ], now()->addMinutes(10));
    }

    private function clearPreview(Request $request): void
    {
        $token = $request->session()->pull('student_import_preview');
        if (is_string($token) && preg_match('/^[0-9a-f-]{36}$/i', $token) === 1) {
            Storage::disk('local')->delete($this->previewPath($token));
        }
    }

    private function removeExpiredPreviews(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files('student-import-previews') as $path) {
            if (preg_match('#^student-import-previews/[0-9a-f-]{36}\.json$#i', $path) === 1
                && $disk->lastModified($path) < time() - 86400) {
                $disk->delete($path);
            }
        }
    }

    private function rowsFromSheet(Worksheet $sheet): iterable
    {
        foreach ($sheet->getRowIterator() as $row) {
            $values = [];
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);

            foreach ($cells as $cell) {
                $column = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                $values[$column] = $cell->getFormattedValue();
            }

            if ($values !== []) {
                yield $values;
            }
        }
    }

    private function findColumn(array $headers, array $aliases): ?int
    {
        foreach ($headers as $index => $header) {
            $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $header)));
            if (in_array($normalized, $aliases, true)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeGender(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if (in_array($value, ['P', 'PEREMPUAN', 'WANITA', 'F', 'FEMALE'], true)) {
            return 'P';
        }
        if (in_array($value, ['L', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA', 'M', 'MALE'], true)) {
            return 'L';
        }

        return null;
    }

    private function normalizeInduk(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{1,3}(?:[,. ]\d{3})+$/', $value) === 1) {
            $value = preg_replace('/\D+/', '', $value) ?? '';
        }

        return preg_match('/^\d+$/', $value) === 1 ? $value : '';
    }

    private function studentNumberPrefix(string $induk): string
    {
        $digits = preg_replace('/\D+/', '', $induk) ?? '';

        return strlen($digits) >= 3 ? substr($digits, 0, 3) : '';
    }

    private function gradeFromClassName(string $className): string
    {
        return preg_match('/^(XII|XI|X)(?:\s|$)/i', trim($className), $matches) === 1
            ? strtoupper($matches[1])
            : '';
    }

    private function classFromSheetName(string $sheetName): string
    {
        return preg_match('/(?:^|\s)(XII|XI|X)(?:\s+.+)?$/i', trim($sheetName), $matches) === 1
            ? trim($matches[0])
            : '';
    }
}
