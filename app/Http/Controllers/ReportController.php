<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\AppSetting;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$start, $end, $month, $year, $period, $weekDate] = $this->period($request);
        $className = trim((string) $request->query('kelas', ''));
        $search = trim((string) $request->query('cari', ''));
        $workDays = $this->workDays($start, $end);
        $students = $this->studentTotals($start, $end, $className, $search);
        $totals = ['alpha' => 0, 'izin' => 0, 'sakit' => 0, 'hadir' => 0, 'haid' => 0];

        foreach ($students as &$student) {
            $student['hadir'] = (int) $student['hadir'];
            $student['percentage'] = $workDays ? round($student['hadir'] / $workDays * 100, 1) : 0;
            foreach ($totals as $key => $_value) {
                $totals[$key] += $student[$key];
            }
        }
        unset($student);

        $totalRecords = count($students);
        $overallPercentage = $totalRecords && $workDays
            ? round($totals['hadir'] / ($totalRecords * $workDays) * 100, 1)
            : 0;
        $previousStart = $period === 'minggu'
            ? $start->modify('-7 days')
            : $start->modify('first day of last month');
        $previousEnd = $period === 'minggu'
            ? $previousStart->modify('+4 days')
            : $previousStart->modify('last day of this month');
        $todayDate = new DateTimeImmutable(now()->toDateString());
        $trendEnd = $todayDate >= $start && $todayDate <= $end ? $todayDate : $end;
        $elapsedDays = $start->diff($trendEnd)->days + 1;
        $previousComparableEnd = $previousStart->modify('+'.($elapsedDays - 1).' days');
        if ($previousComparableEnd < $previousEnd) {
            $previousEnd = $previousComparableEnd;
        }
        $trendTotals = $trendEnd == $end
            ? $totals
            : $this->periodAttendanceTotals($start, $trendEnd, $className, $search);
        $trendWorkDays = $this->workDays($start, $trendEnd);
        $previousWorkDays = $this->workDays($previousStart, $previousEnd);
        $previousTotals = $this->periodAttendanceTotals($previousStart, $previousEnd, $className, $search);
        $currentDailyRate = $trendWorkDays ? $trendTotals['hadir'] / $trendWorkDays : 0;
        $previousDailyRate = $previousWorkDays ? $previousTotals['hadir'] / $previousWorkDays : 0;
        $currentDailyAverage = round($currentDailyRate, 1);
        $previousDailyAverage = round($previousDailyRate, 1);
        $trendPercent = $previousDailyRate > 0
            ? round((($currentDailyRate - $previousDailyRate) / $previousDailyRate) * 100)
            : null;
        $chartMax = max(1, ...array_values($totals));
        $monthNames = $this->monthNames();
        $classRanking = DB::table('siswa as s')
            ->join('kelas as k', 'k.id', '=', 's.id_kelas')
            ->leftJoin('absensi as a', function ($join) use ($start, $end): void {
                $join->on('a.id_siswa', '=', 's.id')->whereBetween('a.tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            })
            ->select('k.nama as kelas')
            ->selectRaw('COUNT(DISTINCT s.id) as total_siswa, COALESCE(SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END), 0) as total_alpha', ['A'])
            ->groupBy('k.id', 'k.nama')->orderByDesc('total_alpha')->orderBy('k.nama')->get();
        $classes = Classroom::query()->orderBy('nama')->pluck('nama');
        $scanStartTime = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_START)->value('value');
        $scanEndTime = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_END)->value('value');

        return view('reports.index', compact(
            'start', 'end', 'month', 'year', 'period', 'weekDate', 'className', 'search', 'workDays',
            'students', 'totals', 'totalRecords', 'overallPercentage', 'chartMax', 'classRanking', 'classes', 'monthNames', 'scanStartTime', 'scanEndTime',
            'previousStart', 'previousEnd', 'trendEnd', 'previousTotals', 'currentDailyAverage', 'previousDailyAverage', 'trendPercent'
        ));
    }

    public function updateScanCutoff(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scan_start' => ['required', 'date_format:H:i'],
            'scan_end' => ['required', 'date_format:H:i', 'after:scan_start'],
        ], [
            'scan_end.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        DB::transaction(function () use ($data): void {
            AppSetting::query()->updateOrCreate(
                ['key' => AppSetting::ATTENDANCE_SCAN_START],
                ['value' => $data['scan_start']]
            );
            AppSetting::query()->updateOrCreate(
                ['key' => AppSetting::ATTENDANCE_SCAN_END],
                ['value' => $data['scan_end']]
            );
        });

        return redirect()->route('reports.index')->with('status', 'Rentang waktu scan berhasil disimpan.');
    }

    public function alpha(Request $request): View
    {
        [$start, $end, $month, $year, $period, $weekDate] = $this->period($request);
        $className = trim((string) $request->query('kelas', ''));
        $search = trim((string) $request->query('cari', ''));
        $absences = DB::table('absensi as a')
            ->join('siswa as s', 's.id', '=', 'a.id_siswa')
            ->join('kelas as k', 'k.id', '=', 's.id_kelas')
            ->where('a.status', 'A')
            ->whereBetween('a.tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($className !== '', fn ($query) => $query->where('k.nama', $className))
            ->when($search !== '', fn ($query) => $query->where('s.nama', 'like', '%'.$search.'%'))
            ->select('s.id', 's.induk', 's.nama', 's.jenis_kelamin', 's.is_irma', 's.is_nonis', 'k.nama as kelas', 'a.tanggal')
            ->orderBy('k.nama')->orderBy('s.nama')->orderBy('a.tanggal')->get();
        $rows = $absences->groupBy('id')->map(function ($studentAbsences): object {
            $student = $studentAbsences->first();
            $student->total_alpha = $studentAbsences->count();
            $student->tanggal_alpha = $studentAbsences->map(fn ($absence) => (new DateTimeImmutable($absence->tanggal))->format('d/m'))->implode(', ');

            return $student;
        })->sortBy([['total_alpha', 'desc'], ['kelas', 'asc'], ['nama', 'asc']])->values();
        $totalAlpha = $rows->sum('total_alpha');
        $classes = Classroom::query()->orderBy('nama')->pluck('nama');
        $monthNames = $this->monthNames();

        return view('reports.alpha', compact('start', 'end', 'month', 'year', 'period', 'weekDate', 'className', 'search', 'rows', 'totalAlpha', 'classes', 'monthNames'));
    }

    private function monthNames(): array
    {
        return [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }

    private function period(Request $request): array
    {
        $month = max(1, min(12, $request->integer('bulan', (int) date('n'))));
        $year = max(2020, min(2100, $request->integer('tahun', (int) date('Y'))));
        $period = $request->query('periode') === 'minggu' ? 'minggu' : 'bulan';
        $weekDate = (string) $request->query('minggu', date('Y-m-d'));
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');

        if ($period === 'minggu') {
            $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $weekDate);
            if (! $anchor || $anchor->format('Y-m-d') !== $weekDate) {
                $anchor = new DateTimeImmutable('today');
            }
            $weekDate = $anchor->format('Y-m-d');
            $start = $anchor->modify('monday this week');
            $end = $start->modify('+4 days');
        }

        return [$start, $end, $month, $year, $period, $weekDate];
    }

    private function workDays(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $count = 0;
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $count += (int) ($date->format('N') <= 5);
        }

        return $count;
    }

    private function periodAttendanceTotals(DateTimeImmutable $start, DateTimeImmutable $end, string $className, string $search): array
    {
        $totals = DB::table('absensi as a')
            ->join('siswa as s', 's.id', '=', 'a.id_siswa')
            ->join('kelas as k', 'k.id', '=', 's.id_kelas')
            ->whereBetween('a.tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($className !== '', fn ($query) => $query->where('k.nama', $className))
            ->when($search !== '', fn ($query) => $query->where('s.nama', 'like', '%'.$search.'%'))
            ->selectRaw('COALESCE(SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END), 0) as hadir', ['H'])
            ->selectRaw('COUNT(*) as tercatat')
            ->first();

        return ['hadir' => (int) $totals->hadir, 'tercatat' => (int) $totals->tercatat];
    }

    private function studentTotals(DateTimeImmutable $start, DateTimeImmutable $end, string $className, string $search): array
    {
        return DB::table('siswa as s')
            ->join('kelas as k', 'k.id', '=', 's.id_kelas')
            ->leftJoin('absensi as a', function ($join) use ($start, $end): void {
                $join->on('a.id_siswa', '=', 's.id')->whereBetween('a.tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            })
            ->when($className !== '', fn ($query) => $query->where('k.nama', $className))
            ->when($search !== '', fn ($query) => $query->where('s.nama', 'like', '%'.$search.'%'))
            ->select('s.induk', 's.nama', 's.jenis_kelamin', 's.is_irma', 's.is_nonis', 'k.nama as kelas')
            ->selectRaw('COALESCE(SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END), 0) as alpha', ['A'])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END), 0) as izin', ['I'])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END), 0) as sakit', ['S'])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END), 0) as hadir', ['H'])
            ->selectRaw('COALESCE(SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END), 0) as haid', ['M'])
            ->selectRaw('MAX(CASE WHEN a.status = ? THEN a.waktu_scan END) as scan_hadir_terakhir', ['H'])
            ->groupBy('s.id', 's.induk', 's.nama', 's.jenis_kelamin', 's.is_irma', 's.is_nonis', 'k.nama')
            ->orderByDesc('alpha')->orderBy('k.nama')->orderBy('s.nama')->get()
            ->map(fn ($row) => [
                'induk' => $row->induk,
                'nama' => $row->nama,
                'jenis_kelamin' => $row->jenis_kelamin,
                'is_irma' => $row->is_irma,
                'is_nonis' => $row->is_nonis,
                'kelas' => $row->kelas,
                'alpha' => (int) $row->alpha,
                'izin' => (int) $row->izin,
                'sakit' => (int) $row->sakit,
                'hadir' => (int) $row->hadir,
                'haid' => (int) $row->haid,
                'scan_hadir_terakhir' => $row->scan_hadir_terakhir
                    ? Carbon::parse($row->scan_hadir_terakhir)->format('d/m/Y H:i:s')
                    : null,
            ])
            ->all();
    }
}
