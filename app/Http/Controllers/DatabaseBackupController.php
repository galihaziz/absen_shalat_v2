<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DatabaseBackupController extends Controller
{
    public function index(): View
    {
        DB::table('backup_schedule_settings')->insertOrIgnore([
            'id' => 1,
            'enabled' => false,
            'interval_value' => 1,
            'interval_unit' => 'days',
            'next_run_at' => null,
            'updated_by' => null,
            'updated_at' => now(),
        ]);
        $schedule = DB::table('backup_schedule_settings')->where('id', 1)->first();
        $archives = DB::table('backup_archives')
            ->leftJoin('users', 'backup_archives.created_by', '=', 'users.id')
            ->select('backup_archives.*', 'users.nama as creator_name')
            ->orderByDesc('backup_archives.created_at')
            ->paginate(15, ['*'], 'backups');

        $logs = DB::table('backup_logs')
            ->leftJoin('users', 'backup_logs.actor_id', '=', 'users.id')
            ->select('backup_logs.*', 'users.nama as actor_name')
            ->orderByDesc('backup_logs.created_at')
            ->paginate(20, ['*'], 'logs');

        return view('backups.index', compact('archives', 'logs', 'schedule'));
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'interval_value' => ['required', 'integer', 'min:1', 'max:365'],
            'interval_unit' => ['required', 'in:days,weeks,months'],
        ]);

        $enabled = (bool) $data['enabled'];
        $nextRunAt = $enabled
            ? $this->nextRunAt((int) $data['interval_value'], $data['interval_unit'])
            : null;

        DB::table('backup_schedule_settings')->updateOrInsert(
            ['id' => 1],
            [
                'enabled' => $enabled,
                'interval_value' => (int) $data['interval_value'],
                'interval_unit' => $data['interval_unit'],
                'next_run_at' => $nextRunAt,
                'updated_by' => (int) $request->user()->id,
                'updated_at' => now(),
            ]
        );

        return redirect()->route('backups.index')->with(
            'status',
            $enabled
                ? 'Backup otomatis diaktifkan. Backup berikutnya dijadwalkan pada '.$nextRunAt->format('d/m/Y H:i').'.'
                : 'Backup otomatis dinonaktifkan.'
        );
    }

    public function store(DatabaseBackupService $backups): RedirectResponse
    {
        $archive = $backups->createBackup((int) auth()->id());

        return redirect()->route('backups.index')
            ->with('status', 'Backup database berhasil dibuat (#'.$archive->id.').');
    }

    public function restore(int $archive, DatabaseBackupService $backups): RedirectResponse
    {
        $safetyArchive = $backups->restoreBackup($archive, (int) auth()->id());

        return redirect()->route('backups.index')->with(
            'status',
            'Data berhasil dipulihkan dari backup #'.$archive.'. Backup pengaman data sebelum pemulihan adalah #'.$safetyArchive->id.'.'
        );
    }

    private function nextRunAt(int $interval, string $unit): \Illuminate\Support\Carbon
    {
        $nextRunAt = now();

        return match ($unit) {
            'weeks' => $nextRunAt->addWeeks($interval),
            'months' => $nextRunAt->addMonthsNoOverflow($interval),
            default => $nextRunAt->addDays($interval),
        };
    }
}
