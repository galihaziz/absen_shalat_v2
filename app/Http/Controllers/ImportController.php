<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportController extends Controller
{
    public function create(): View
    {
        return view('students.import', ['progressToken' => (string) Str::uuid()]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $data = $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls,ods', 'max:10240'],
            'progress_token' => ['nullable', 'uuid'],
        ]);
        $progressToken = ($data['progress_token'] ?? null) ?: (string) Str::uuid();
        $progressKey = $this->progressCacheKey((int) $request->user()->getAuthIdentifier(), $progressToken);
        Cache::put($progressKey, [
            'percent' => 5,
            'stage' => 'Membaca workbook',
            'detail' => 'File diterima. Server sedang membaca sheet.',
            'complete' => false,
        ], 1800);

        $reader = IOFactory::createReaderForFile($data['excel']->getRealPath());
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $workbook = $reader->load($data['excel']->getRealPath());
        $previewRows = [];
        $classNames = [];
        $sheetCount = max(1, count($workbook->getAllSheets()));

        foreach ($workbook->getAllSheets() as $sheetIndex => $sheet) {
            $sheetName = $sheet->getTitle();
            if (preg_match('/lulus|template|link/i', $sheetName)) {
                continue;
            }
            $currentClass = $this->classFromSheetName($sheetName);
            $columns = ['nama' => null, 'induk' => null, 'jenis_kelamin' => null, 'kelas' => null];
            $hasStudentHeaders = false;

            foreach ($this->rowsFromSheet($sheet) as $rowNumber => $row) {
                $detected = [
                    'nama' => $this->findColumn($row, [
                        'nama', 'name', 'namasiswa', 'namalengkap', 'namapesertadidik', 'pesertadidik', 'namamurid', 'murid', 'siswa',
                    ]),
                    'induk' => $this->findColumn($row, [
                        'induk', 'nomorinduk', 'induksiswa', 'nis', 'nisn', 'noinduk', 'noinduksiswa', 'nomorinduksiswa', 'nik', 'nomorinduknasional',
                    ]),
                    'jenis_kelamin' => $this->findColumn($row, [
                        'jeniskelamin', 'jk', 'lp', 'gender', 'sex', 'jeniskelaminlp', 'jeniskelaminlkpr', 'jeniskelaminprlk', 'lkpr', 'prlk',
                    ]),
                    'kelas' => $this->findColumn($row, [
                        'kelas', 'rombel', 'class', 'rombonganbelajar', 'tingkatrombel', 'rombelkelas', 'namakelas', 'tingkat', 'namarombel',
                    ]),
                ];

                if ($detected['nama'] !== null || $detected['induk'] !== null) {
                    $hasStudentHeaders = true;
                    if ($detected['nama'] !== null) {
                        $columns['nama'] = $detected['nama'];
                    }
                    if ($detected['induk'] !== null) {
                        $columns['induk'] = $detected['induk'];
                    }
                    foreach (['jenis_kelamin', 'kelas'] as $column) {
                        if ($detected[$column] !== null) {
                            $columns[$column] = $detected[$column];
                        }
                    }
                    continue;
                }
                if ($hasStudentHeaders && $columns['nama'] !== null && $columns['induk'] !== null) {
                    $nameValue = trim((string) ($row[$columns['nama']] ?? ''));
                    $indukValue = trim((string) ($row[$columns['induk']] ?? ''));
                    if ($nameValue === '' && $indukValue === '') {
                        $currentClass = $this->classFromRow($row) ?: $currentClass;
                    }
                }
                if (! $hasStudentHeaders || $columns['induk'] === null || $columns['nama'] === null) {
                    $currentClass = $this->classFromRow($row) ?: $currentClass;
                    continue;
                }

                $name = trim((string) ($row[$columns['nama']] ?? ''));
                $induk = $this->normalizeInduk((string) ($row[$columns['induk']] ?? ''));
                if ($name === '' && $induk === '') {
                    continue;
                }

                $gender = $this->normalizeGender((string) ($row[$columns['jenis_kelamin']] ?? ''));
                if ($gender === null) {
                    foreach ($row as $cell) {
                        $gender = $this->normalizeGender((string) $cell);
                        if ($gender !== null) {
                            break;
                        }
                    }
                }

                $rawClass = $columns['kelas'] !== null ? trim((string) ($row[$columns['kelas']] ?? '')) : '';
                $className = $this->resolveClassName($rawClass, $currentClass);
                $errors = [];
                if ($induk === '') {
                    $errors[] = 'Nomor induk wajib berupa angka.';
                }
                if ($name === '' || is_numeric($name)) {
                    $errors[] = 'Nama siswa tidak valid.';
                }
                if ($gender === null) {
                    $errors[] = 'Jenis kelamin belum diisi atau tidak dikenali.';
                }
                if ($className === '') {
                    $errors[] = 'Kelas tidak dapat dikenali dari kolom atau nama sheet.';
                } elseif ($this->isIncompleteClassName($className)) {
                    $errors[] = 'Nama kelas belum lengkap. Pastikan tingkat dan rombel tercantum, misalnya X TL 1.';
                }

                if ($className !== '') {
                    $classNames[$className] = true;
                }
                $previewRows[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'induk' => $induk,
                    'nama' => $name,
                    'jenis_kelamin' => $gender ?? '',
                    'kelas' => $className,
                    'grade' => $this->classGrade($className),
                    'induk_prefix' => substr($induk, 0, 3),
                    'errors' => $errors,
                    'record_id' => null,
                ];
            }

            Cache::put($progressKey, [
                'percent' => min(90, 10 + (int) floor((($sheetIndex + 1) / $sheetCount) * 80)),
                'stage' => 'Membaca '.$sheetName,
                'detail' => 'Memeriksa data dan mendeteksi kelas pada workbook.',
                'complete' => false,
            ], 1800);
        }

        if ($previewRows === []) {
            Cache::forget($progressKey);
            return redirect()->route('students.import')
                ->withErrors(['excel' => 'Tidak ada baris siswa yang dapat dibaca. Pastikan workbook memiliki header nomor induk dan nama siswa.']);
        }

        $indukCounts = [];
        foreach ($previewRows as $row) {
            if ($row['induk'] !== '') {
                $indukCounts[$row['induk']] = ($indukCounts[$row['induk']] ?? 0) + 1;
            }
        }

        $validCount = 0;
        $invalidCount = 0;
        foreach ($previewRows as $index => &$row) {
            if (($indukCounts[$row['induk']] ?? 0) > 1) {
                $row['errors'][] = 'Nomor induk duplikat di dalam workbook.';
            }
            if ($row['errors'] === []) {
                $row['record_id'] = $index;
                $validCount++;
            } else {
                $invalidCount++;
            }
        }
        unset($row);

        $previewToken = (string) Str::uuid();
        Cache::put($this->previewCacheKey((int) $request->user()->getAuthIdentifier(), $previewToken), [
            'rows' => $previewRows,
            'classes' => array_keys($classNames),
        ], 1800);
        Cache::put($progressKey, [
            'percent' => 100,
            'stage' => 'Pratinjau siap',
            'detail' => 'Data workbook siap diperiksa sebelum disimpan.',
            'complete' => true,
        ], 1800);

        return view('students.import', [
            'progressToken' => $progressToken,
            'previewToken' => $previewToken,
            'previewRows' => $previewRows,
            'validCount' => $validCount,
            'invalidCount' => $invalidCount,
        ]);
    }

    public function progress(Request $request, string $progressToken): JsonResponse
    {
        $state = Cache::get($this->progressCacheKey((int) $request->user()->getAuthIdentifier(), $progressToken));

        return response()->json($state);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preview_token' => ['required', 'uuid'],
            'selected_rows_json' => ['nullable', 'string'],
            'selected_rows' => ['nullable', 'array'],
            'selected_rows.*' => ['integer', 'min:0'],
            'sync_alumni' => ['nullable', 'boolean'],
        ]);
        if (isset($data['selected_rows_json'])) {
            try {
                $decodedSelectedIds = json_decode($data['selected_rows_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages([
                    'selected_rows_json' => 'Daftar pilihan siswa tidak valid. Muat ulang pratinjau dan coba lagi.',
                ]);
            }

            if (
                ! is_array($decodedSelectedIds)
                || ! array_is_list($decodedSelectedIds)
                || array_filter($decodedSelectedIds, static fn ($id): bool => ! is_int($id) || $id < 0) !== []
            ) {
                throw ValidationException::withMessages([
                    'selected_rows_json' => 'Daftar pilihan siswa tidak valid. Muat ulang pratinjau dan coba lagi.',
                ]);
            }
            $data['selected_rows'] = $decodedSelectedIds;
        }

        $cacheKey = $this->previewCacheKey((int) $request->user()->getAuthIdentifier(), $data['preview_token']);
        $preview = Cache::get($cacheKey);
        if (! is_array($preview)) {
            return redirect()->route('students.import')->withErrors([
                'preview_token' => 'Pratinjau sudah kedaluwarsa. Unggah workbook kembali.',
            ]);
        }

        $selectedIds = array_fill_keys(array_map('intval', array_unique($data['selected_rows'] ?? [])), true);
        $selectedRows = array_values(array_filter(
            $preview['rows'],
            static fn (array $row): bool => $row['record_id'] !== null && isset($selectedIds[$row['record_id']])
        ));
        if ($selectedRows === []) {
            return redirect()->route('students.import')->withErrors([
                'preview_token' => 'Pilih setidaknya satu siswa valid dari pratinjau.',
            ]);
        }

        $result = DB::transaction(function () use ($selectedRows, $preview, $data): array {
            $classNames = array_values(array_unique(array_column($selectedRows, 'kelas')));
            $classIds = Classroom::query()->whereIn('nama', $classNames)->pluck('id', 'nama')->all();
            $missingClasses = array_values(array_diff($classNames, array_keys($classIds)));
            if ($missingClasses !== []) {
                Classroom::query()->insertOrIgnore(array_map(
                    static fn (string $name): array => ['nama' => $name],
                    $missingClasses
                ));
                $classIds = Classroom::query()->whereIn('nama', $classNames)->pluck('id', 'nama')->all();
            }

            $indukNumbers = array_values(array_unique(array_column($selectedRows, 'induk')));
            $existingStudents = collect();
            foreach (array_chunk($indukNumbers, 500) as $chunk) {
                $existingStudents = $existingStudents->concat(
                    Student::query()
                        ->whereIn('induk', $chunk)
                        ->get(['id', 'induk', 'is_alumni'])
                );
            }
            $studentsByInduk = $existingStudents->keyBy('induk');
            $updates = [];
            $inserts = [];
            $defaultPortalPinHash = Hash::make((string) config('app.student_default_pin', 'siswa123'));
            foreach ($selectedRows as $row) {
                $student = $studentsByInduk->get($row['induk']);
                $studentData = [
                    'induk' => $row['induk'],
                    'nama' => $row['nama'],
                    'jenis_kelamin' => $row['jenis_kelamin'],
                    'id_kelas' => (int) $classIds[$row['kelas']],
                    'is_alumni' => false,
                ];
                if ($student !== null) {
                    $updates[] = ['id' => $student->id] + $studentData;
                } else {
                    $inserts[] = $studentData + ['portal_pin' => $defaultPortalPinHash];
                }
            }

            foreach (array_chunk($updates, 100) as $chunk) {
                DB::table('siswa')->upsert($chunk, ['id'], ['nama', 'jenis_kelamin', 'id_kelas', 'is_alumni']);
            }
            foreach (array_chunk($inserts, 100) as $chunk) {
                DB::table('siswa')->insert($chunk);
            }

            $alumni = 0;
            if (($data['sync_alumni'] ?? false) && count(array_filter(
                $preview['rows'],
                static fn (array $row): bool => $row['errors'] !== []
            )) === 0 && count($selectedRows) === count(array_filter(
                $preview['rows'],
                static fn (array $row): bool => $row['record_id'] !== null
            ))) {
                $syncClassNames = array_values(array_unique($preview['classes']));
                $syncClassIds = Classroom::query()->whereIn('nama', $syncClassNames)->pluck('id')->all();
                if ($syncClassIds !== []) {
                    $importedInduk = array_column($selectedRows, 'induk');
                    $alumni = DB::table('siswa')
                        ->whereIn('id_kelas', $syncClassIds)
                        ->where('is_alumni', false)
                        ->whereNotIn('induk', $importedInduk)
                        ->update(['is_alumni' => true]);
                }
            }

            return ['added' => count($inserts), 'updated' => count($updates), 'alumni' => $alumni];
        });

        Cache::forget($cacheKey);

        return redirect()->route('students.import')->with('status', sprintf(
            'Import selesai. %d siswa baru, %d siswa diperbarui, %d siswa ditandai alumni. Riwayat absensi tetap tersimpan.',
            $result['added'], $result['updated'], $result['alumni']
        ));
    }

    private function progressCacheKey(int $userId, string $token): string
    {
        return "student-import-progress:{$userId}:{$token}";
    }

    private function previewCacheKey(int $userId, string $token): string
    {
        return "student-import-preview:{$userId}:{$token}";
    }

    private function classFromRow(array $row): string
    {
        $cells = array_values(array_filter(
            array_map(static fn ($cell): string => trim((string) $cell), $row),
            static fn (string $cell): bool => $cell !== ''
        ));
        foreach (array_merge($cells, [implode(' ', $cells)]) as $value) {
            if (preg_match('/(?:^|[\s:;,])(?:KELAS|ROMBEL|ROMBONGAN\s+BELAJAR|CLASS)\s*[:=.-]?\s*((?:XII|XI|X|12|11|10)(?:[\s\-_]+[A-Z0-9]+)*)\b/i', $value, $matches) === 1) {
                return $this->normalizeClass(trim($matches[1]));
            }
            if (preg_match('/^(?:XII|XI|X|12|11|10)(?:[\s\-_]+[A-Z0-9]+)*$/i', $value) === 1) {
                return $this->normalizeClass($value);
            }
        }

        return '';
    }

    private function resolveClassName(string $rawClass, string $sheetClass): string
    {
        $normalizedClass = $rawClass !== '' ? $this->normalizeClass($rawClass) : '';
        if ($normalizedClass === '') {
            return $sheetClass;
        }

        if ($sheetClass !== '' && $this->classGrade($sheetClass) !== '') {
            if ($this->classGrade($normalizedClass) === '') {
                return $sheetClass;
            }
            if (
                $this->classGrade($normalizedClass) === $this->classGrade($sheetClass)
                && (
                    preg_match('/^(?:XII|XI|X)$/i', $normalizedClass) === 1
                    || str_starts_with(strtoupper($sheetClass), strtoupper($normalizedClass).' ')
                )
            ) {
                return $sheetClass;
            }
        }

        return $normalizedClass;
    }

    private function classGrade(string $className): string
    {
        if (preg_match('/^(XII|XI|X)\b/i', trim($className), $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    private function isIncompleteClassName(string $className): bool
    {
        return preg_match('/^(?:XII|XI|X)\s+\S+/i', trim($className)) !== 1;
    }

    private function rowsFromSheet(Worksheet $sheet): iterable
    {
        foreach ($sheet->getRowIterator() as $row) {
            $values = [];
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);

            foreach ($cells as $cell) {
                $column = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                $rawVal = $cell->getFormattedValue();
                if ($rawVal instanceof RichText) {
                    $rawVal = $rawVal->getPlainText();
                }
                $values[$column] = $rawVal;
            }

            if ($values !== []) {
                yield $row->getRowIndex() => $values;
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

    private function studentIdentity(string $name, string $gender, int $classId): string
    {
        return $classId."\0".$gender."\0".$name;
    }

    private function normalizeGender(string $value): ?string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
        if (in_array($normalized, ['p', 'perempuan', 'wanita', 'f', 'female', 'pr'], true)) {
            return 'P';
        }
        if (in_array($normalized, ['l', 'lakilaki', 'laki', 'pria', 'm', 'male', 'lk'], true)) {
            return 'L';
        }

        return null;
    }

    private function normalizeInduk(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (stripos($value, 'e+') !== false && is_numeric($value)) {
            $value = sprintf('%.0f', (float) $value);
        }

        if (preg_match('/^(\d+)\.0+$/', $value, $matches)) {
            $value = $matches[1];
        }

        $cleaned = trim(str_replace([',', '.', ' ', '-', "'", '`'], '', $value));

        return preg_match('/^\d+$/', $cleaned) === 1 ? $cleaned : '';
    }

    private function normalizeClass(string $className): string
    {
        $className = trim($className);
        $className = preg_replace('/^(?:KELAS\s+)?10(?=[\s\-_]|$)/i', 'X', $className);
        $className = preg_replace('/^(?:KELAS\s+)?11(?=[\s\-_]|$)/i', 'XI', $className);
        $className = preg_replace('/^(?:KELAS\s+)?12(?=[\s\-_]|$)/i', 'XII', $className);
        $className = preg_replace('/^KELAS\s+/i', '', $className);

        return trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $className)));
    }

    private function classFromSheetName(string $sheetName): string
    {
        $sheetName = trim($sheetName);
        if (preg_match('/(?:^|\s)(XII|XI|X|12|11|10)(?:[\s\-_].*)?$/i', $sheetName, $matches)) {
            return $this->normalizeClass(trim($matches[0]));
        }

        return '';
    }
}
