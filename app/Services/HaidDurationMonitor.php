<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HaidDurationMonitor
{
    public function activeOverLimit(): Collection
    {
        $today = new DateTimeImmutable(now()->toDateString());
        $previousSchoolDay = $today->modify('-1 day');
        while ((int) $previousSchoolDay->format('N') > 5) {
            $previousSchoolDay = $previousSchoolDay->modify('-1 day');
        }

        $latestAttendance = DB::table('absensi')
            ->select('id_siswa')
            ->selectRaw('MAX(tanggal) as latest_date')
            ->groupBy('id_siswa');

        $candidates = DB::table('siswa as s')
            ->joinSub($latestAttendance, 'latest', function ($join): void {
                $join->on('latest.id_siswa', '=', 's.id');
            })
            ->join('absensi as latest_a', function ($join): void {
                $join->on('latest_a.id_siswa', '=', 's.id')
                    ->on('latest_a.tanggal', '=', 'latest.latest_date');
            })
            ->join('kelas as k', 'k.id', '=', 's.id_kelas')
            ->where('s.jenis_kelamin', 'P')
            ->where('s.is_alumni', false)
            ->where('latest_a.status', 'M')
            ->whereBetween('latest.latest_date', [$previousSchoolDay->format('Y-m-d'), $today->format('Y-m-d')])
            ->select('s.id', 's.induk', 's.nama', 'k.nama as kelas', 'latest.latest_date')
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $haidDates = DB::table('absensi')
            ->whereIn('id_siswa', $candidates->pluck('id'))
            ->where('status', 'M')
            ->where('tanggal', '<=', $today->format('Y-m-d'))
            ->orderBy('id_siswa')
            ->orderByDesc('tanggal')
            ->get(['id_siswa', 'tanggal'])
            ->groupBy('id_siswa');

        return $candidates->map(function ($student) use ($haidDates, $today): ?array {
            $dates = $haidDates->get($student->id, collect())->pluck('tanggal');
            $latestDate = new DateTimeImmutable($student->latest_date);
            $streakStart = $latestDate;
            $newerDate = $latestDate;

            foreach ($dates->skip(1) as $date) {
                $olderDate = new DateTimeImmutable($date);
                $expectedNextSchoolDay = $olderDate->modify(
                    $olderDate->format('N') === '5' ? '+3 days' : '+1 day'
                );

                if ($expectedNextSchoolDay->format('Y-m-d') !== $newerDate->format('Y-m-d')) {
                    break;
                }

                $streakStart = $olderDate;
                $newerDate = $olderDate;
            }

            $days = $streakStart->diff($today)->days + 1;
            if ($days <= 15) {
                return null;
            }

            return [
                'induk' => $student->induk,
                'nama' => $student->nama,
                'kelas' => $student->kelas,
                'start_date' => $streakStart->format('Y-m-d'),
                'last_haid_date' => $latestDate->format('Y-m-d'),
                'days' => $days,
            ];
        })->filter()->sortByDesc('days')->values();
    }
}
