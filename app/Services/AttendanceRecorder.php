<?php

namespace App\Services;

use AbsenShalat\Models\Attendance;
use AbsenShalat\Models\Student;
use Illuminate\Support\Carbon;

class AttendanceRecorder
{
    /**
     * @return array{attendance: Attendance, already_present: bool, already_recorded: bool}
     */
    public function record(Student $student, string $status, ?Carbon $now = null): array
    {
        $now ??= now();
        $date = $now->toDateString();
        $attendance = Attendance::query()->firstOrNew(['id_siswa' => $student->id, 'tanggal' => $date]);
        $alreadyPresent = $attendance->exists && $attendance->status === 'H';
        $alreadyRecorded = $attendance->exists && $attendance->status === $status;
        $scanTime = $alreadyRecorded ? ($attendance->waktu_scan ?? $now) : $now;

        $attendance = Attendance::query()->updateOrCreate(
            ['id_siswa' => $student->id, 'tanggal' => $date],
            ['status' => $status, 'waktu_scan' => $scanTime]
        );

        return [
            'attendance' => $attendance,
            'already_present' => $alreadyPresent,
            'already_recorded' => $alreadyRecorded,
        ];
    }
}
