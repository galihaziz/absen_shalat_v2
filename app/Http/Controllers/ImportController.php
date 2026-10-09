<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ImportController extends Controller
{
    public function create(): View
    {
        return view('students.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,ods', 'max:10240'],
        ]);
        $workbook = IOFactory::load($data['excel']->getRealPath());
        $sheets = [];
        foreach ($workbook->getAllSheets() as $sheet) {
            $sheets[] = ['name' => $sheet->getTitle(), 'rows' => $sheet->toArray(null, false, false, false)];
        }

        $result = DB::transaction(function () use ($sheets): array {
            $added = 0;
            $updated = 0;
            $importedSheets = 0;
            $importedInduk = [];

            foreach ($sheets as $sheet) {
                if (preg_match('/lulus|template|link/i', $sheet['name'])) {
                    continue;
                }
                $currentClass = $this->classFromSheetName($sheet['name']);
                $columns = ['nama' => null, 'induk' => null, 'jenis_kelamin' => null, 'kelas' => null];
                $sheetImported = false;

                foreach ($sheet['rows'] as $row) {
                    $detected = [
                        'nama' => $this->findColumn($row, ['nama', 'name', 'namasiswa']),
                        'induk' => $this->findColumn($row, ['induk', 'nomorinduk', 'induksiswa']),
                        'jenis_kelamin' => $this->findColumn($row, ['jeniskelamin', 'jk', 'lp', 'gender', 'sex']),
                        'kelas' => $this->findColumn($row, ['kelas', 'rombel', 'class']),
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
                        $detectedGender = $this->findColumn($row, ['jeniskelamin', 'jk', 'lp', 'gender', 'sex']);
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
                    if ($currentClass === '') {
                        continue;
                    }

                    $name = trim((string) ($row[$columns['nama']] ?? ''));
                    $induk = $this->normalizeInduk((string) ($row[$columns['induk']] ?? ''));
                    $className = trim((string) ($row[$columns['kelas']] ?? '')) ?: $currentClass;
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

                    $class = Classroom::query()->firstOrCreate(['nama' => $className]);
                    $student = Student::query()->where('induk', $induk)->first();
                    if ($student) {
                        $student->update(['nama' => $name, 'jenis_kelamin' => $gender, 'id_kelas' => $class->id, 'is_alumni' => false]);
                        $updated++;
                    } else {
                        $student = Student::query()->firstOrCreate(
                            ['nama' => $name, 'jenis_kelamin' => $gender, 'id_kelas' => $class->id],
                            ['induk' => $induk, 'is_alumni' => false]
                        );
                        $added += (int) $student->wasRecentlyCreated;
                    }
                    $importedInduk[$induk] = true;
                    $sheetImported = true;
                }

                $importedSheets += (int) $sheetImported;
            }

            if ($importedSheets === 0) {
                throw new RuntimeException('Tidak ada data siswa yang cocok. Pastikan file memiliki kolom induk, nama, jenis kelamin, dan kelas atau nama sheet memuat kelas.');
            }

            $alumni = Student::query()
                ->where('is_alumni', false)
                ->whereNotIn('induk', array_keys($importedInduk))
                ->update(['is_alumni' => true]);

            return ['sheets' => $importedSheets, 'added' => $added, 'updated' => $updated, 'alumni' => $alumni];
        });

        return redirect()->route('students.import')->with('status', sprintf(
            'Import selesai dari %d sheet kelas. %d siswa baru, %d siswa diperbarui, %d siswa ditandai alumni. Riwayat absensi tetap tersimpan.',
            $result['sheets'], $result['added'], $result['updated'], $result['alumni']
        ));
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
        $value = trim(str_replace([',', '.'], '', $value));

        return preg_match('/^\d+$/', $value) === 1 ? $value : '';
    }

    private function classFromSheetName(string $sheetName): string
    {
        return preg_match('/(?:^|\s)(XII|XI|X)(?:\s+.+)?$/i', trim($sheetName), $matches) === 1
            ? trim($matches[0])
            : '';
    }
}
