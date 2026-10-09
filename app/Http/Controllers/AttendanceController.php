<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Attendance;
use AbsenShalat\Models\AppSetting;
use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function home(Request $request): View|RedirectResponse
    {
        return app(StudentPortalController::class)->create($request);
    }

    public function scanner(): View
    {
        $now = now();
        $scanStartTime = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_START)->value('value');
        $scanEndTime = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_END)->value('value');
        $scanWindowConfigured = $scanStartTime !== null && $scanEndTime !== null;
        $currentTime = $now->format('H:i');

        return view('attendance.scan', [
            'scanDate' => $now,
            'scanStartTime' => $scanStartTime,
            'scanEndTime' => $scanEndTime,
            'scanClosed' => $scanWindowConfigured && ($currentTime < $scanStartTime || $currentTime > $scanEndTime),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'induk' => ['required', 'string', 'regex:/^\d+$/', 'max:30'],
            'jenis' => ['nullable', 'in:haid'],
        ]);
        $isHaid = ($data['jenis'] ?? null) === 'haid';
        $now = now();
        $scanStartTime = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_START)->value('value');
        $scanEndTime = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_END)->value('value');
        $currentTime = $now->format('H:i');

        if ($scanStartTime !== null && $scanEndTime !== null && ($currentTime < $scanStartTime || $currentTime > $scanEndTime)) {
            return response()->json([
                'success' => false,
                'message' => 'Scan QR hanya dibuka pukul '.$scanStartTime.' sampai '.$scanEndTime.'.',
            ], 403);
        }

        $matches = Student::query()
            ->with('classroom')
            ->where('is_alumni', false)
            ->where('induk', $data['induk'])
            ->limit(2)
            ->get();

        if ($matches->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Siswa dengan nomor induk tersebut tidak ditemukan.'], 404);
        }

        if ($matches->count() !== 1) {
            return response()->json(['success' => false, 'message' => 'Nomor induk tidak unik; absensi tidak disimpan.'], 409);
        }

        $student = $matches->first();
        if ($isHaid && $student->jenis_kelamin !== 'P') {
            return response()->json([
                'success' => false,
                'message' => 'QR haid hanya berlaku untuk siswi.',
            ], 422);
        }

        $date = $now->toDateString();
        $attendance = Attendance::query()->firstOrNew(['id_siswa' => $student->id, 'tanggal' => $date]);
        $alreadyPresent = $attendance->exists && $attendance->status === 'H';
        $status = $isHaid ? 'M' : 'H';
        $alreadyRecorded = $attendance->exists && $attendance->status === $status;
        $scanTime = $alreadyRecorded ? ($attendance->waktu_scan ?? $now) : $now;
        Attendance::query()->updateOrCreate(
            ['id_siswa' => $student->id, 'tanggal' => $date],
            ['status' => $status, 'waktu_scan' => $scanTime]
        );

        return response()->json([
            'success' => true,
            'already_present' => $alreadyPresent,
            'already_recorded' => $alreadyRecorded,
            'message' => $isHaid
                ? ($alreadyRecorded ? 'Keterangan M sudah tercatat hari ini.' : 'Keterangan: M berhasil dicatat.')
                : ($alreadyPresent ? 'Siswa sudah tercatat hadir hari ini.' : 'Kehadiran berhasil dicatat.'),
            'student' => ['nama' => $student->nama, 'induk' => $student->induk, 'kelas' => $student->classroom->nama],
            'tanggal' => $date,
            'waktu_scan' => $scanTime->format('H:i:s'),
            'keterangan' => $isHaid ? 'M' : 'H',
        ]);
    }

    public function index(Request $request): View|JsonResponse
    {
        $month = max(1, min(12, (int) $request->query('bulan', date('n'))));
        $year = max(2020, min(2100, (int) $request->query('tahun', date('Y'))));
        $selectedClass = trim((string) $request->query('kelas', ''));
        $search = trim((string) $request->query('cari', ''));
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');
        $dates = [];
        $weeks = [];

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            if ((int) $date->format('N') > 5) {
                continue;
            }
            $dates[] = $date;
            $weekKey = $date->modify('monday this week')->format('Y-m-d');
            $weeks[$weekKey]['dates'][] = $date;
        }

        foreach ($weeks as $index => &$week) {
            $week['label'] = sprintf(
                'Minggu %d (%s - %s)',
                array_search($index, array_keys($weeks), true) + 1,
                $week['dates'][0]->format('d/m'),
                end($week['dates'])->format('d/m')
            );
        }
        unset($week);

        $selectedTableWeek = (string) $request->query('minggu_tabel', 'all');
        if ($selectedTableWeek !== 'all' && ! isset($weeks[$selectedTableWeek])) {
            $selectedTableWeek = 'all';
        }
        $visibleDates = $selectedTableWeek === 'all' ? $dates : $weeks[$selectedTableWeek]['dates'];
        $classes = Classroom::query()
            ->whereHas('students', fn ($query) => $query->where('is_alumni', false))
            ->orderBy('nama')->pluck('nama');
        $today = now()->toDateString();
        $todayStudentCount = Student::query()->where('is_alumni', false)->count();
        $todayStatuses = DB::table('absensi as a')
            ->join('siswa as s', 's.id', '=', 'a.id_siswa')
            ->where('s.is_alumni', false)
            ->where('a.tanggal', $today)
            ->selectRaw('a.status, COUNT(DISTINCT a.id_siswa) as total')
            ->groupBy('a.status')
            ->pluck('total', 'status');
        $todayTotals = [
            'hadir' => (int) ($todayStatuses['H'] ?? 0),
            'alpha' => (int) ($todayStatuses['A'] ?? 0),
            'izin' => (int) ($todayStatuses['I'] ?? 0),
            'sakit' => (int) ($todayStatuses['S'] ?? 0),
            'haid' => (int) ($todayStatuses['M'] ?? 0),
        ];
        $todayRecordedCount = array_sum($todayTotals);
        $todayUnmarkedCount = max(0, $todayStudentCount - $todayRecordedCount);
        $todayScannedCount = Attendance::query()
            ->where('tanggal', $today)->where('status', 'H')->whereNotNull('waktu_scan')->count();
        $lastScanAt = Attendance::query()
            ->where('tanggal', $today)->whereNotNull('waktu_scan')->max('waktu_scan');
        $classProgress = DB::table('kelas as k')
            ->join('siswa as s', function ($join): void {
                $join->on('s.id_kelas', '=', 'k.id')->where('s.is_alumni', false);
            })
            ->leftJoin('absensi as a', function ($join) use ($today): void {
                $join->on('a.id_siswa', '=', 's.id')->where('a.tanggal', $today);
            })
            ->select('k.nama as kelas')
            ->selectRaw('COUNT(DISTINCT s.id) as total_siswa')
            ->selectRaw('COUNT(DISTINCT CASE WHEN a.status IS NOT NULL THEN s.id END) as tercatat')
            ->groupBy('k.id', 'k.nama')
            ->orderBy('k.nama')
            ->get()
            ->map(function ($progress): object {
                $classroom = new Classroom(['nama' => $progress->kelas]);
                $progress->tingkat = $classroom->gradeLabel() ?? '';
                $progress->jurusan = $classroom->majorLabel();

                return $progress;
            });
        $progressGrades = $classProgress->pluck('tingkat')->filter()->unique()
            ->sortBy(fn (string $grade): int => ['X' => 10, 'XI' => 11, 'XII' => 12][$grade])
            ->values();
        $progressMajors = $classProgress->pluck('jurusan')->unique()->sort()->values();
        $students = collect();
        $attendance = [];
        $attendanceTimes = [];

        if ($selectedClass !== '') {
            $students = Student::query()
                ->where('is_alumni', false)
                ->whereHas('classroom', fn ($query) => $query->where('nama', $selectedClass))
                ->when($search !== '', fn ($query) => $query->where('nama', 'like', '%'.$search.'%'))
                ->orderBy('nama')
                ->orderBy('id')
                ->simplePaginate(50)
                ->withQueryString();

            if ($students->isNotEmpty()) {
                Attendance::query()
                    ->whereIn('id_siswa', $students->pluck('id'))
                    ->whereBetween('tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                    ->get(['id_siswa', 'tanggal', 'status', 'waktu_scan'])
                    ->each(function (Attendance $record) use (&$attendance, &$attendanceTimes): void {
                        $attendance[$record->id_siswa][$record->tanggal] = $record->status;
                        if ($record->waktu_scan !== null) {
                            $attendanceTimes[$record->id_siswa][$record->tanggal] = $record->waktu_scan->format('H:i:s');
                        }
                    });
            }
        }

        $attendanceTotals = [];
        foreach ($students as $student) {
            $monthly = ['A' => 0, 'I' => 0, 'S' => 0, 'H' => 0, 'M' => 0];
            foreach ($dates as $date) {
                $status = $attendance[$student->id][$date->format('Y-m-d')] ?? '';
                if (isset($monthly[$status])) {
                    $monthly[$status]++;
                }
            }

            $attendanceTotals[$student->id] = ['monthly' => $monthly, 'weekly' => []];
            foreach ($weeks as $weekKey => $week) {
                $weekly = ['A' => 0, 'I' => 0, 'S' => 0, 'H' => 0, 'M' => 0];
                foreach ($week['dates'] as $date) {
                    $status = $attendance[$student->id][$date->format('Y-m-d')] ?? '';
                    if (isset($weekly[$status])) {
                        $weekly[$status]++;
                    }
                }
                $attendanceTotals[$student->id]['weekly'][$weekKey] = $weekly;
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('attendance._rows', compact('students', 'attendance', 'attendanceTimes', 'attendanceTotals', 'visibleDates', 'weeks', 'selectedTableWeek'))->render(),
                'nextPageUrl' => $students->nextPageUrl(),
                'count' => $students->count(),
            ]);
        }

        $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return view('attendance.index', compact(
            'month', 'year', 'selectedClass', 'search', 'start', 'end', 'dates', 'weeks',
            'selectedTableWeek', 'visibleDates', 'classes', 'students', 'attendance', 'attendanceTimes', 'attendanceTotals', 'monthNames',
            'today', 'todayStudentCount', 'todayTotals', 'todayUnmarkedCount', 'todayScannedCount', 'lastScanAt',
            'classProgress', 'progressGrades', 'progressMajors'
        ));
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_siswa' => ['required', 'integer', 'exists:siswa,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'status' => ['present', 'nullable', 'in:H,A,I,S,M'],
        ]);

        $status = $data['status'] ?? '';
        if ($status === 'M' && Student::query()->whereKey($data['id_siswa'])->value('jenis_kelamin') !== 'P') {
            return response()->json(['success' => false, 'message' => 'Status haid hanya berlaku untuk siswi.'], 422);
        }

        $attendanceKey = ['id_siswa' => $data['id_siswa'], 'tanggal' => $data['tanggal']];
        if ($status === '') {
            Attendance::query()->where($attendanceKey)->delete();

            return response()->json(['success' => true, 'status' => '']);
        }

        Attendance::query()->updateOrCreate($attendanceKey, ['status' => $status, 'waktu_scan' => null]);

        return response()->json(['success' => true]);
    }

    public function delete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
            'kelas' => ['required', 'string', 'max:50'],
        ]);
        $start = sprintf('%04d-%02d-01', $data['tahun'], $data['bulan']);
        $end = date('Y-m-t', strtotime($start));
        $query = Attendance::query()->whereBetween('tanggal', [$start, $end]);

        if ($data['kelas'] !== 'semua') {
            $query->whereHas('student.classroom', fn ($classQuery) => $classQuery->where('nama', $data['kelas']));
        }

        return response()->json(['success' => true, 'deleted' => $query->delete()]);
    }
}
