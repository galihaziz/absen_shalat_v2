<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DatabaseBackupController extends Controller
{
    public function index(): View
    {
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

        return view('backups.index', compact('archives', 'logs'));
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
}
