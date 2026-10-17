<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use App\Services\StudentCardPhotoProcessor;
use App\Services\DatabaseBackupService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('students:photos-optimize', function (StudentCardPhotoProcessor $photoProcessor): int {
    $disk = Storage::disk('local');
    $paths = $disk->allFiles('foto-siswa');
    $processed = 0;
    $skipped = 0;
    $bytesBefore = 0;
    $bytesAfter = 0;

    foreach ($paths as $path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }

        $contents = $disk->get($path);
        $optimized = $photoProcessor->optimizeForStorage($contents, $extension);
        if ($optimized === null) {
            $this->warn("Dilewati karena tidak dapat dikompres di bawah 100 KiB: {$path}");
            $skipped++;
            $bytesBefore += strlen($contents);
            $bytesAfter += strlen($contents);
            continue;
        }

        $newPath = pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME).'.'.$optimized['extension'];
        $samePathIgnoringCase = strcasecmp($newPath, $path) === 0;

        if (strlen($optimized['contents']) >= strlen($contents)) {
            $skipped++;
            $bytesBefore += strlen($contents);
            $bytesAfter += strlen($contents);
            continue;
        }

        if (! $samePathIgnoringCase && $newPath !== $path && $disk->exists($newPath)) {
            $this->warn("Dilewati karena file tujuan sudah ada: {$path}");
            $skipped++;
            $bytesBefore += strlen($contents);
            $bytesAfter += strlen($contents);
            continue;
        }

        $writePath = $samePathIgnoringCase ? $path : $newPath;
        if (! $disk->put($writePath, $optimized['contents'])) {
            $this->warn("Gagal menulis hasil kompresi: {$path}");
            $skipped++;
            $bytesBefore += strlen($contents);
            $bytesAfter += strlen($contents);
            continue;
        }

        if (! $samePathIgnoringCase && $newPath !== $path) {
            $disk->delete($path);
        }

        $processed++;
        $bytesBefore += strlen($contents);
        $bytesAfter += strlen($optimized['contents']);
    }

    $saved = max(0, $bytesBefore - $bytesAfter);
    $this->info("Selesai: {$processed} foto dikompres, {$skipped} dilewati.");
    $this->info('Penghematan: '.number_format($saved / 1024 / 1024, 2).' MB.');

    return self::SUCCESS;
})->purpose('Compress existing student photos in Laravel storage');

Artisan::command('backups:run-scheduled', function (DatabaseBackupService $backups): int {
    $now = now();
    $schedule = DB::table('backup_schedule_settings')
        ->where('id', 1)
        ->where('enabled', true)
        ->where('next_run_at', '<=', $now)
        ->first();

    if ($schedule === null) {
        $this->info('Tidak ada backup otomatis yang jatuh tempo.');
        return self::SUCCESS;
    }

    $nextRunAt = match ($schedule->interval_unit) {
        'weeks' => $now->copy()->addWeeks((int) $schedule->interval_value),
        'months' => $now->copy()->addMonthsNoOverflow((int) $schedule->interval_value),
        default => $now->copy()->addDays((int) $schedule->interval_value),
    };
    $claimed = DB::table('backup_schedule_settings')
        ->where('id', 1)
        ->where('enabled', true)
        ->where('next_run_at', '<=', $now)
        ->update(['next_run_at' => $nextRunAt, 'updated_at' => $now]);

    if ($claimed === 0) {
        $this->info('Backup otomatis sudah diproses oleh scheduler lain.');
        return self::SUCCESS;
    }

    try {
        $archive = $backups->createBackup(null, 'scheduled_backup');
    } catch (\Throwable $error) {
        DB::table('backup_schedule_settings')
            ->where('id', 1)
            ->where('next_run_at', $nextRunAt)
            ->update(['next_run_at' => now()->addMinute(), 'updated_at' => now()]);
        report($error);
        $this->error('Backup otomatis gagal. Jadwal akan dicoba kembali pada proses berikutnya.');

        return self::FAILURE;
    }

    $this->info('Backup otomatis berhasil dibuat (#'.$archive->id.').');

    return self::SUCCESS;
})->purpose('Create a database backup when the configured schedule is due');

Schedule::command('backups:run-scheduled')->everyMinute()->withoutOverlapping();
