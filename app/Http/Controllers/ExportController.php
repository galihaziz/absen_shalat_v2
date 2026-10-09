<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Attendance;
use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup as WorksheetPageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Writer\Word2007;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function excel(Request $request): Response
    {
        $period = $request->query('tipe') === 'bulan' ? 'bulan' : 'minggu';
        if ($period === 'bulan') {
            $month = max(1, min(12, $request->integer('bulan', (int) date('n'))));
            $year = max(2020, min(2100, $request->integer('tahun', (int) date('Y'))));
            $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
            $end = $start->modify('last day of this month');
            $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            $periodLabel = $monthNames[$month].' '.$year;
            $periodName = sprintf('bulan-%04d-%02d', $year, $month);
        } else {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query('minggu', date('Y-m-d')));
            $start = ($date ?: new DateTimeImmutable('today'))->modify('monday this week');
            $end = $start->modify('+4 days');
            $periodLabel = $start->format('d/m/Y').' - '.$end->format('d/m/Y');
            $periodName = 'minggu-'.$start->format('Y-m-d');
        }

        $dates = $this->dates($start, $end, $period === 'bulan');
        $className = trim((string) $request->query('kelas', ''));
        $students = Student::query()->where('is_alumni', false)->with('classroom')
            ->when($className !== '' && strtolower($className) !== 'semua', fn ($query) => $query->whereHas('classroom', fn ($classQuery) => $classQuery->where('nama', $className)))
            ->orderBy(Classroom::query()->select('nama')->whereColumn('kelas.id', 'siswa.id_kelas'))
            ->orderBy('nama')->get();
        $attendance = $this->attendanceMap($students, $start, $end);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        if ($className === '' || strtolower($className) === 'semua') {
            foreach (['X' => 'Angkatan X', 'XI' => 'Angkatan XI', 'XII' => 'Angkatan XII'] as $grade => $title) {
                $gradeStudents = $students->filter(fn (Student $student) => preg_match('/^'.$grade.'\\b/i', $student->classroom->nama) === 1);
                $this->addAttendanceSheet($spreadsheet, $title, $gradeStudents, $dates, $attendance, false, $periodLabel);
            }
        } else {
            $this->addAttendanceSheet($spreadsheet, 'Rekap', $students, $dates, $attendance, true, $periodLabel);
        }
        $this->addAlphaSheet($spreadsheet, $students, $dates, $attendance, $periodLabel);

        $exportAll = $className === '' || strtolower($className) === 'semua';

        return $this->xlsxResponse($spreadsheet, 'rekap-'.($exportAll ? 'semua-kelas' : $this->slug($className)).'-'.$periodName.'.xlsx');
    }

    public function printForm(Request $request): View
    {
        $monthStart = new DateTimeImmutable(date('Y-m-01'));
        $defaultStart = $monthStart->modify('monday this week');
        $start = $this->validDate((string) $request->query('dari')) ?: $defaultStart;
        $end = $start->modify('+13 days');
        $format = strtolower((string) $request->query('format', 'xlsx')) === 'docx' ? 'docx' : 'xlsx';

        return view('attendance.print', [
            'classes' => Classroom::query()
                ->whereHas('students', fn ($query) => $query->where('is_alumni', false))
                ->orderBy('nama')->pluck('nama'),
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'format' => $format,
        ]);
    }

    public function print(Request $request): Response
    {
        $data = $request->validate([
            'dari' => ['required', 'date_format:Y-m-d'],
            'format' => ['required', 'in:xlsx,docx'],
        ]);
        $start = new DateTimeImmutable($data['dari']);
        $end = $start->modify('+13 days');
        $dates = $this->dates($start, $end);
        abort_if(count($dates) === 0, 422, 'Rentang tanggal harus mencakup setidaknya satu hari kerja.');
        abort_if(count($dates) > 10, 422, 'Rentang cetak maksimal 10 hari kerja agar muat di A4 portrait tanpa scaling.');

        $classes = Classroom::query()->with(['students' => fn ($query) => $query->orderBy('nama')])->orderBy('nama')->get();

        if ($data['format'] === 'docx') {
            return $this->docxResponse($classes, $dates, $start, $end);
        }

        $spreadsheet = $this->buildA4Workbook($classes, $dates, $start, $end);

        return $this->xlsxResponse($spreadsheet, sprintf('absen-shalat-%s-sampai-%s.xlsx', $start->format('d-m-Y'), $end->format('d-m-Y')));
    }

    private function buildA4Workbook(Collection $classes, array $dates, DateTimeImmutable $start, DateTimeImmutable $end): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);
        $classesByGrade = ['X' => [], 'XI' => [], 'XII' => []];

        foreach ($classes as $class) {
            if (preg_match('/^(XII|XI|X)(?:\s|$)/i', trim($class->nama), $matches) === 1) {
                $classesByGrade[strtoupper($matches[1])][] = $class;
            }
        }

        foreach ($classesByGrade as $grade => $gradeClasses) {
            $this->addA4GradeWorksheet($spreadsheet, $grade, $gradeClasses, $dates, $start, $end);
        }

        return $spreadsheet;
    }

    private function addA4GradeWorksheet(Spreadsheet $spreadsheet, string $grade, array $classes, array $dates, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Angkatan '.$grade);
        $lastColumn = Coordinate::stringFromColumnIndex(count($dates) + 3);
        $row = 1;

        foreach ($classes as $classIndex => $class) {
            if ($classIndex > 0) {
                $worksheet->setBreak('A'.($row - 1), Worksheet::BREAK_ROW);
            }

            $row = $this->writeA4ClassBlock($worksheet, $row, $class, $dates, $start, $end, $lastColumn) + 1;
        }

        if ($classes === []) {
            $worksheet->mergeCells('A1:'.$lastColumn.'1');
            $worksheet->setCellValue('A1', 'Tidak ada rombel untuk angkatan '.$grade);
        }

        $worksheet->getColumnDimension('A')->setWidth(5);
        $worksheet->getColumnDimension('B')->setWidth(27);
        $worksheet->getColumnDimension('C')->setWidth(5);
        for ($columnIndex = 4; $columnIndex <= count($dates) + 3; $columnIndex++) {
            $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(6.2);
        }
        $worksheet->getPageSetup()->setPaperSize(WorksheetPageSetup::PAPERSIZE_A4)
            ->setOrientation(WorksheetPageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setFitToPage(true)
            ->setScale(null, false)
            ->setPrintArea('A1:'.$lastColumn.max(1, $row - 1));
        $worksheet->getPageMargins()->setTop(0.2)->setRight(0.2)->setLeft(0.2)->setBottom(0.2);
    }

    private function writeA4ClassBlock(Worksheet $worksheet, int $startRow, Classroom $class, array $dates, DateTimeImmutable $start, DateTimeImmutable $end, string $lastColumn): int
    {
        $titleRow = $startRow;
        $periodRow = $startRow + 1;
        $groupHeaderRow = $startRow + 3;
        $dayHeaderRow = $startRow + 4;
        $firstStudentRow = $startRow + 5;

        $worksheet->mergeCells("A{$titleRow}:{$lastColumn}{$titleRow}");
        $worksheet->setCellValue("A{$titleRow}", 'ROMBEL: '.$class->nama);
        $worksheet->mergeCells("A{$periodRow}:{$lastColumn}{$periodRow}");
        $worksheet->setCellValue("A{$periodRow}", sprintf('PERIODE: %s - %s', $start->format('d/m/Y'), $end->format('d/m/Y')));
        foreach ([["A{$groupHeaderRow}:A{$dayHeaderRow}", 'NO'], ["B{$groupHeaderRow}:B{$dayHeaderRow}", 'NAMA'], ["C{$groupHeaderRow}:C{$dayHeaderRow}", 'L/P']] as [$range, $label]) {
            $worksheet->mergeCells($range);
            $worksheet->setCellValue(explode(':', $range)[0], $label);
        }

        $weekGroups = [];
        foreach ($dates as $index => $date) {
            $column = Coordinate::stringFromColumnIndex($index + 4);
            $weekKey = $date->modify('monday this week')->format('Y-m-d');
            $weekGroups[$weekKey]['first'] ??= $column;
            $weekGroups[$weekKey]['last'] = $column;
            $worksheet->setCellValue($column.$dayHeaderRow, $this->weekdayLabel($date));
        }
        $weekNumber = 1;
        foreach ($weekGroups as $columns) {
            if ($columns['first'] !== $columns['last']) {
                $worksheet->mergeCells($columns['first'].$groupHeaderRow.':'.$columns['last'].$groupHeaderRow);
            }
            $worksheet->setCellValue($columns['first'].$groupHeaderRow, 'MINGGU '.$weekNumber++);
        }

        $students = $class->students;
        foreach ($students as $studentIndex => $student) {
            $row = $firstStudentRow + $studentIndex;
            $worksheet->setCellValue('A'.$row, $studentIndex + 1);
            $worksheet->getCell('B'.$row)->setValueExplicit((string) $student->nama, DataType::TYPE_STRING);
            $worksheet->getCell('C'.$row)->setValueExplicit((string) $student->jenis_kelamin, DataType::TYPE_STRING);
        }

        $lastRow = max($dayHeaderRow, $firstStudentRow + $students->count() - 1);
        $worksheet->getStyle("A{$groupHeaderRow}:{$lastColumn}{$lastRow}")->applyFromArray([
            'font' => ['color' => ['rgb' => '000000'], 'size' => 9],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFFFF']],
        ]);
        foreach ($students as $studentIndex => $student) {
            if ($student->is_irma || $student->is_nonis) {
                $irmaRow = $firstStudentRow + $studentIndex;
                $worksheet->getStyle("A{$irmaRow}:{$lastColumn}{$irmaRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($student->is_nonis ? 'E5EFFF' : 'D9F2DF');
            }
        }
        if ($students->isNotEmpty()) {
            $worksheet->getStyle("B{$firstStudentRow}:B{$lastRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setShrinkToFit(true)->setWrapText(false);
        }
        $worksheet->getStyle("A{$titleRow}:{$lastColumn}{$titleRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $worksheet->getStyle("A{$periodRow}:{$lastColumn}{$periodRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $worksheet->getStyle("A{$groupHeaderRow}:{$lastColumn}{$dayHeaderRow}")->getFont()->setBold(true);
        $worksheet->getRowDimension($titleRow)->setRowHeight(22);
        $worksheet->getRowDimension($periodRow)->setRowHeight(17);
        $worksheet->getRowDimension($groupHeaderRow)->setRowHeight(18);
        $worksheet->getRowDimension($dayHeaderRow)->setRowHeight(30);
        $studentRowHeight = $students->isNotEmpty() ? max(7, min(24, floor(680 / $students->count()))) : 16;
        for ($row = $firstStudentRow; $row <= $lastRow; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight($studentRowHeight);
        }

        return $lastRow;
    }

    private function weekdayLabel(DateTimeImmutable $date): string
    {
        return [1 => 'SEN', 2 => 'SEL', 3 => 'RAB', 4 => 'KAM', 5 => 'JUM'][(int) $date->format('N')]."\n".$date->format('d/m');
    }

    private function addAttendanceSheet(Spreadsheet $book, string $title, Collection $students, array $dates, array $attendance, bool $includeClass, string $periodLabel): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);
        $rows = [['Rekap Absensi Solat - '.$title.' - '.$periodLabel]];
        $highlightRows = [];
        $groups = $students->groupBy(fn (Student $student) => $student->classroom->nama);

        foreach ($groups as $class => $classStudents) {
            $rows[] = [$class];
            $headers = ['NO', 'NAMA', 'L/P'];
            if ($includeClass) {
                $headers[] = 'KELAS';
            }
            foreach ($dates as $date) {
                $headers[] = $date->format('d/m');
            }
            array_push($headers, 'TOTAL A', 'TOTAL I', 'TOTAL S', 'TOTAL H', 'TOTAL M (HAID)');
            $rows[] = $headers;
            foreach ($classStudents as $index => $student) {
                $dayValues = array_map(fn (DateTimeImmutable $date) => $attendance[$student->id][$date->format('Y-m-d')] ?? '-', $dates);
                $dayStatuses = array_map(fn (DateTimeImmutable $date) => $attendance[$student->id][$date->format('Y-m-d')] ?? '', $dates);
                $row = [$index + 1, $student->nama, $student->jenis_kelamin];
                if ($includeClass) {
                    $row[] = $student->classroom->nama;
                }
                array_push($row, ...$dayValues);
                foreach (['A', 'I', 'S', 'H', 'M'] as $status) {
                    $row[] = count(array_filter($dayStatuses, fn ($item) => $item === $status));
                }
                if ($student->is_irma || $student->is_nonis) {
                    $highlightRows[count($rows) + 1] = $student->is_nonis ? 'E5EFFF' : 'D9F2DF';
                }
                $rows[] = $row;
            }
        }

        $columnCount = $this->writeAttendanceRows($sheet, $rows, $highlightRows);
        for ($column = 1; $column <= $columnCount; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
    }

    private function addAlphaSheet(Spreadsheet $book, Collection $students, array $dates, array $attendance, string $periodLabel): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Siswa Alfa');
        $rows = [['Siswa Alfa - '.$periodLabel]];
        $highlightRows = [];
        $alphaStudents = $students->filter(fn (Student $student) => collect($dates)->contains(fn (DateTimeImmutable $date) => ($attendance[$student->id][$date->format('Y-m-d')] ?? '') === 'A'));
        foreach ($alphaStudents->groupBy(fn (Student $student) => $student->classroom->nama) as $class => $classStudents) {
            $rows[] = ['KELAS '.$class];
            $rows[] = ['NO', 'NAMA', 'L/P', 'KELAS', ...array_map(fn (DateTimeImmutable $date) => $date->format('d/m'), $dates), 'TOTAL A'];
            foreach ($classStudents as $index => $student) {
                $statuses = array_map(fn (DateTimeImmutable $date) => $attendance[$student->id][$date->format('Y-m-d')] ?? '-', $dates);
                $rows[] = [$index + 1, $student->nama, $student->jenis_kelamin, $class, ...$statuses, count(array_filter($statuses, fn ($status) => $status === 'A'))];
                if ($student->is_irma || $student->is_nonis) {
                    $highlightRows[count($rows)] = $student->is_nonis ? 'E5EFFF' : 'D9F2DF';
                }
            }
            $rows[] = [];
            $rows[] = [];
        }
        if ($alphaStudents->isEmpty()) {
            $rows[] = ['Tidak ada siswa alfa pada minggu ini.'];
        }
        $columnCount = $this->writeAttendanceRows($sheet, $rows, $highlightRows);
        foreach (range('A', Coordinate::stringFromColumnIndex($columnCount)) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeAttendanceRows(Worksheet $sheet, array $rows, array $highlightRows): int
    {
        $columnCount = 1;
        foreach ($rows as $rowIndex => $row) {
            $columnCount = max($columnCount, count($row));
            foreach ($row as $columnIndex => $value) {
                $cellAddress = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1);
                if (is_int($value) || is_float($value)) {
                    $sheet->getCell($cellAddress)->setValue($value);
                } else {
                    $sheet->getCell($cellAddress)->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
            }
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true)->setSize(14);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        foreach ($highlightRows as $rowNumber => $color) {
            $sheet->getStyle('A'.$rowNumber.':'.$lastColumn.$rowNumber)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        }

        return $columnCount;
    }

    private function docxResponse(Collection $classes, array $dates, DateTimeImmutable $start, DateTimeImmutable $end): Response
    {
        $word = new PhpWord;
        $word->getSettings()->setThemeFontLang(new Language('id-ID'));
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(8);
        $tableStyleName = 'AttendanceTable';
        $word->addTableStyle($tableStyleName, [
            'borderSize' => 5,
            'borderColor' => '000000',
            'cellMargin' => 35,
            'layout' => 'fixed',
        ], ['bgColor' => 'FFFFFF']);
        $weekHeaderStyleName = 'AttendanceWeekHeader';
        $word->addTableStyle($weekHeaderStyleName, [
            'borderSize' => 0,
            'cellMargin' => 35,
            'layout' => 'fixed',
        ]);

        foreach ($classes as $classIndex => $class) {
            $sectionStyle = [
                'paperSize' => 'A4', 'orientation' => 'portrait', 'marginLeft' => 450,
                'marginRight' => 450, 'marginTop' => 420, 'marginBottom' => 420,
            ];
            if ($classIndex > 0) {
                $sectionStyle['breakType'] = 'nextPage';
            }
            $section = $word->addSection($sectionStyle);
            $section->addText('LEMBAR ABSENSI', ['bold' => true, 'size' => 13], ['spaceAfter' => 30]);
            $section->addText('ROMBEL: '.$class->nama, ['bold' => true, 'size' => 9], ['spaceAfter' => 20]);
            $section->addText(sprintf('PERIODE: %s - %s', $start->format('d/m/Y'), $end->format('d/m/Y')), ['size' => 8], ['spaceAfter' => 70]);

            $weekHeader = $section->addTable($weekHeaderStyleName);
            $weekHeader->addRow(280, ['cantSplit' => true]);
            foreach ([430, 2850, 420] as $identityWidth) {
                $weekHeader->addCell($identityWidth, ['valign' => 'center'])->addText('', ['size' => 6], ['spaceAfter' => 0]);
            }
            foreach (['MINGGU 1', 'MINGGU 2'] as $weekLabel) {
                $weekHeader->addCell(3600, ['valign' => 'center'])->addText(
                    $weekLabel,
                    ['bold' => true, 'size' => 7, 'color' => '000000'],
                    ['alignment' => 'center', 'spaceAfter' => 0, 'spaceBefore' => 0]
                );
            }

            $table = $section->addTable($tableStyleName);
            $table->addRow();
            $headers = [
                ['text' => 'NO', 'width' => 430],
                ['text' => 'NAMA', 'width' => 2850],
                ['text' => 'L/P', 'width' => 420],
            ];
            foreach ($dates as $date) {
                $headers[] = ['text' => $this->weekdayLabel($date)."\n".$date->format('d/m'), 'width' => 720];
            }
            foreach ($headers as $header) {
                $table->addCell($header['width'], ['valign' => 'center'])->addText(
                    $header['text'],
                    ['bold' => true, 'size' => 6.5, 'color' => '000000'],
                    ['alignment' => 'center', 'spaceAfter' => 0, 'spaceBefore' => 0]
                );
            }

            foreach ($class->students as $index => $student) {
                $table->addRow(250, ['cantSplit' => true]);
                $values = [
                    ['text' => (string) ($index + 1), 'width' => 430, 'align' => 'center'],
                    ['text' => (string) $student->nama, 'width' => 2850, 'align' => 'left'],
                    ['text' => (string) $student->jenis_kelamin, 'width' => 420, 'align' => 'center'],
                ];
                foreach ($dates as $_date) {
                    $values[] = ['text' => '', 'width' => 720, 'align' => 'center'];
                }
                foreach ($values as $value) {
                    $table->addCell($value['width'], [
                        'valign' => 'center',
                        'bgColor' => $student->is_nonis ? 'E5EFFF' : ($student->is_irma ? 'D9F2DF' : 'FFFFFF'),
                    ])->addText(
                        $value['text'],
                        ['size' => 7, 'color' => '000000'],
                        ['alignment' => $value['align'], 'spaceAfter' => 0, 'spaceBefore' => 0]
                    );
                }
            }
        }

        if ($classes->isEmpty()) {
            $section = $word->addSection([
                'paperSize' => 'A4', 'orientation' => 'portrait', 'marginLeft' => 450,
                'marginRight' => 450, 'marginTop' => 420, 'marginBottom' => 420,
            ]);
            $section->addText('Tidak ada rombel untuk diekspor.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'absensi-docx-');
        if ($temporaryFile === false) {
            abort(500, 'File sementara DOCX tidak dapat dibuat.');
        }
        try {
            (new Word2007($word))->save($temporaryFile);
            $content = file_get_contents($temporaryFile);
            if ($content === false) {
                abort(500, 'File DOCX gagal dibaca setelah dibuat.');
            }
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => sprintf('attachment; filename="absen-shalat-%s-sampai-%s.docx"', $start->format('d-m-Y'), $end->format('d-m-Y')),
            'Content-Length' => (string) strlen($content),
        ]);
    }

    private function xlsxResponse(Spreadsheet $spreadsheet, string $filename): Response
    {
        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function attendanceMap(Collection $students, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $attendance = [];
        Attendance::query()->whereIn('id_siswa', $students->pluck('id'))
            ->whereBetween('tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get(['id_siswa', 'tanggal', 'status'])
            ->each(function (Attendance $record) use (&$attendance): void {
                $attendance[$record->id_siswa][$record->tanggal] = $record->status;
            });

        return $attendance;
    }

    private function dates(DateTimeImmutable $start, DateTimeImmutable $end, bool $weekdaysOnly = true): array
    {
        $dates = [];
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            if (! $weekdaysOnly || (int) $date->format('N') <= 5) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function validDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-zA-Z0-9-]+/', '-', $value), '-');
    }
}
