<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use App\Services\StudentCardPhotoProcessor;

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
