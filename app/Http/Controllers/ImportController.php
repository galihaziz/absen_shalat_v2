<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ImportController extends Controller
{
    public function create(): View
    {
        return view('students.import');
    }

    public function store(Request $request): RedirectResponse
    {
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $data = $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls,ods', 'max:10240'],
        ]);
        $reader = IOFactory::createReaderForFile($data['excel']->getRealPath());
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $workbook = $reader->load($data['excel']->getRealPath());
        $sheets = [];
        foreach ($workbook->getAllSheets() as $sheet) {
            $sheets[] = ['name' => $sheet->getTitle(), 'sheet' => $sheet];
        }

        $records = [];
        $importedSheets = 0;

        foreach ($sheets as $sheet) {
            if (preg_match('/lulus|template|link/i', $sheet['name'])) {
                continue;
            }
            $currentClass = $this->classFromSheetName($sheet['name']);
            $columns = ['nama' => null, 'induk' => null, 'jenis_kelamin' => null, 'kelas' => null];
            $sheetImported = false;

            foreach ($this->rowsFromSheet($sheet['sheet']) as $row) {
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

                if ($detected['nama'] === null && $detected['induk'] === null) {
                    $detected['kelas'] = null;
                }
                if ($detected['nama'] === null && $detected['induk'] === null && $detected['kelas'] === null) {
                    foreach ($row as $cell) {
                        if (preg_match('/^(?:KELAS\s+)?(XII|XI|X|12|11|10)(?:[\s\-_].*)?$/i', trim((string) $cell))) {
                            $currentClass = $this->normalizeClass(trim((string) $cell));
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
                    $detectedGender = $this->findColumn($row, [
                        'jeniskelamin', 'jk', 'lp', 'gender', 'sex', 'jeniskelaminlp', 'jeniskelaminlkpr', 'jeniskelaminprlk', 'lkpr', 'prlk',
                    ]);
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
                        if (preg_match('/^(?:KELAS\s+)?(XII|XI|X|12|11|10)(?:[\s\-_].*)?$/i', trim((string) $cell))) {
                            $currentClass = $this->normalizeClass(trim((string) $cell));
                            break;
                        }
                    }

                    continue;
                }

                $rawClass = $columns['kelas'] !== null ? trim((string) ($row[$columns['kelas']] ?? '')) : '';
                $className = $rawClass !== '' ? $this->normalizeClass($rawClass) : $currentClass;
                if ($className === '') {
                    continue;
                }

                $name = trim((string) ($row[$columns['nama']] ?? ''));
                $induk = $this->normalizeInduk((string) ($row[$columns['induk']] ?? ''));
                $gender = $this->normalizeGender((string) ($row[$columns['jenis_kelamin']] ?? ''));
                if ($gender === null) {
                    foreach ($row as $cell) {
                        $gender = $this->normalizeGender((string) $cell);
                        if ($gender !== null) {
                            break;
                        }
                    }
                }
                if ($name === '' || is_numeric($name) || ! preg_match('/^\d+$/', $induk) || $gender === null) {
                    continue;
                }

                $records[] = ['induk' => $induk, 'nama' => $name, 'jenis_kelamin' => $gender, 'kelas' => $className];
                $sheetImported = true;
            }

            $importedSheets += (int) $sheetImported;
        }

        if ($importedSheets === 0) {
            throw new RuntimeException('Tidak ada data siswa yang cocok. Pastikan file memiliki kolom induk, nama, jenis kelamin, dan kelas atau nama sheet memuat kelas.');
        }

        $result = DB::transaction(function () use ($records, $importedSheets): array {
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

            $studentsByInduk = [];
            $studentsByIdentity = [];
            $existingStudents = [];
            foreach (Student::query()->orderBy('id')->get(['id', 'induk', 'nama', 'jenis_kelamin', 'id_kelas', 'is_alumni']) as $student) {
                $studentData = $student->getAttributes();
                $existingStudents[] = $studentData;
                if ($student->induk !== null && ! array_key_exists($student->induk, $studentsByInduk)) {
                    $studentsByInduk[$student->induk] = $studentData;
                }
                $studentsByIdentity[$this->studentIdentity($student->nama, $student->jenis_kelamin, (int) $student->id_kelas)] ??= $studentData;
            }

            $added = 0;
            $updated = 0;
            $importedInduk = [];
            $updatesById = [];
            $newStudents = [];
            $newIndexesByInduk = [];
            $defaultPortalPinHash = null;

            foreach ($records as $record) {
                $classId = (int) $classIds[$record['kelas']];
                $identity = $this->studentIdentity($record['nama'], $record['jenis_kelamin'], $classId);
                $student = $studentsByInduk[$record['induk']] ?? null;

                if (isset($newIndexesByInduk[$record['induk']])) {
                    $index = $newIndexesByInduk[$record['induk']];
                    $oldStudent = $newStudents[$index];
                    $oldIdentity = $this->studentIdentity($oldStudent['nama'], $oldStudent['jenis_kelamin'], (int) $oldStudent['id_kelas']);
                    if (($studentsByIdentity[$oldIdentity]['new_index'] ?? null) === $index) {
                        unset($studentsByIdentity[$oldIdentity]);
                    }
                    $newStudents[$index] = array_merge($oldStudent, [
                        'nama' => $record['nama'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'id_kelas' => $classId,
                        'is_alumni' => false,
                    ]);
                    $studentsByInduk[$record['induk']] = $newStudents[$index];
                    $studentsByIdentity[$identity] = ['new_index' => $index];
                    $updated++;
                } elseif ($student !== null) {
                    $oldIdentity = $this->studentIdentity($student['nama'], $student['jenis_kelamin'], (int) $student['id_kelas']);
                    if (($studentsByIdentity[$oldIdentity]['id'] ?? null) === $student['id']) {
                        unset($studentsByIdentity[$oldIdentity]);
                    }
                    $student = array_merge($student, [
                        'nama' => $record['nama'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'id_kelas' => $classId,
                    ]);
                    $studentsByInduk[$record['induk']] = $student;
                    $studentsByIdentity[$identity] = $student;
                    $updatesById[$student['id']] = [
                        'id' => $student['id'],
                        'nama' => $record['nama'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'id_kelas' => $classId,
                        'is_alumni' => false,
                    ];
                    $updated++;
                } elseif (isset($studentsByIdentity[$identity])) {
                    $student = $studentsByIdentity[$identity];
                } else {
                    $defaultPortalPinHash ??= Hash::make((string) config('app.student_default_pin', 'siswa123'));
                    $index = count($newStudents);
                    $student = [
                        'induk' => $record['induk'],
                        'nama' => $record['nama'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'id_kelas' => $classId,
                        'is_alumni' => false,
                        'portal_pin' => $defaultPortalPinHash,
                    ];
                    $newStudents[] = $student;
                    $newIndexesByInduk[$record['induk']] = $index;
                    $studentsByInduk[$record['induk']] = $student;
                    $studentsByIdentity[$identity] = ['new_index' => $index];
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

            $alumniIds = [];
            foreach ($existingStudents as $student) {
                if (! (bool) $student['is_alumni'] && $student['induk'] !== null && ! isset($importedInduk[$student['induk']])) {
                    $alumniIds[] = $student['id'];
                }
            }
            $alumni = 0;
            foreach (array_chunk($alumniIds, 500) as $chunk) {
                $alumni += DB::table('siswa')->whereIn('id', $chunk)->where('is_alumni', false)->update(['is_alumni' => true]);
            }

            return ['sheets' => $importedSheets, 'added' => $added, 'updated' => $updated, 'alumni' => $alumni];
        });

        return redirect()->route('students.import')->with('status', sprintf(
            'Import selesai dari %d sheet kelas. %d siswa baru, %d siswa diperbarui, %d siswa ditandai alumni. Riwayat absensi tetap tersimpan.',
            $result['sheets'], $result['added'], $result['updated'], $result['alumni']
        ));
    }

    private function rowsFromSheet(Worksheet $sheet): iterable
    {
        foreach ($sheet->getRowIterator() as $row) {
            $values = [];
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);

            foreach ($cells as $cell) {
                $column = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                $rawVal = $cell->getValue();
                if ($rawVal instanceof RichText) {
                    $rawVal = $rawVal->getPlainText();
                }
                $values[$column] = $rawVal;
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
