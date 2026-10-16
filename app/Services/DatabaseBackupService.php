<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    private const TABLES = [
        'kelas',
        'users',
        'app_settings',
        'siswa',
        'absensi',
        'student_face_registrations',
    ];

    private const RESTORE_ORDER = [
        'kelas',
        'users',
        'app_settings',
        'siswa',
        'absensi',
        'student_face_registrations',
    ];

    public function createBackup(int $actorId, string $action = 'backup'): object
    {
        $filename = Str::uuid().'.backup';
        $path = 'private/backups/'.$filename;

        try {
            $tables = [];
            $recordCount = 0;

            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $rows = DB::table($table)->get()->map(
                    static fn (object $row): array => (array) $row
                )->all();
                $tables[$table] = $rows;
                $recordCount += count($rows);
            }

            $contents = json_encode([
                'version' => 1,
                'created_at' => now()->toIso8601String(),
                'tables' => $tables,
            ], JSON_THROW_ON_ERROR);

            if (! Storage::disk('local')->put($path, Crypt::encryptString($contents))) {
                throw new RuntimeException('File backup tidak dapat disimpan.');
            }

            $archiveId = DB::table('backup_archives')->insertGetId([
                'filename' => $filename,
                'created_by' => $actorId,
                'record_count' => $recordCount,
                'created_at' => now(),
            ]);

            $this->log($actorId, $action, 'success', $archiveId, $filename, 'Backup berhasil dibuat.');

            return DB::table('backup_archives')->where('id', $archiveId)->first();
        } catch (Throwable $error) {
            $this->log($actorId, $action, 'failed', null, $filename, 'Backup gagal dibuat.');
            throw $error;
        }
    }

    public function restoreBackup(int $archiveId, int $actorId): object
    {
        $archive = DB::table('backup_archives')->where('id', $archiveId)->first();
        abort_if($archive === null, 404);

        try {
            $path = 'private/backups/'.$archive->filename;
            if (! Storage::disk('local')->exists($path)) {
                throw new RuntimeException('File backup tidak ditemukan di penyimpanan.');
            }

            $contents = Crypt::decryptString(Storage::disk('local')->get($path));
            $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $this->validateSnapshot($snapshot);

            $safetyArchive = $this->createBackup($actorId, 'pre_restore_backup');
            DB::transaction(function () use ($snapshot): void {
                foreach (array_reverse(self::RESTORE_ORDER) as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                foreach (self::RESTORE_ORDER as $table) {
                    if (! Schema::hasTable($table) || ! array_key_exists($table, $snapshot['tables'])) {
                        continue;
                    }

                    $columns = Schema::getColumnListing($table);
                    $rows = array_map(
                        static fn (array $row): array => array_intersect_key($row, array_flip($columns)),
                        $snapshot['tables'][$table]
                    );

                    foreach (array_chunk($rows, 100) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            });

            $this->log(
                $actorId,
                'restore',
                'success',
                (int) $archive->id,
                $archive->filename,
                'Backup dipulihkan. Backup pengaman: #'.$safetyArchive->id.'.'
            );

            return $safetyArchive;
        } catch (Throwable $error) {
            $this->log(
                $actorId,
                'restore',
                'failed',
                (int) $archive->id,
                $archive->filename,
                'Pemulihan backup gagal.'
            );
            throw $error;
        }
    }

    private function validateSnapshot(mixed $snapshot): void
    {
        if (! is_array($snapshot)
            || ($snapshot['version'] ?? null) !== 1
            || ! isset($snapshot['tables'])
            || ! is_array($snapshot['tables'])) {
            throw new RuntimeException('Format file backup tidak valid atau tidak didukung.');
        }

        foreach (self::TABLES as $table) {
            if (! array_key_exists($table, $snapshot['tables']) || ! is_array($snapshot['tables'][$table])) {
                throw new RuntimeException('Data tabel '.$table.' tidak ditemukan di file backup.');
            }
        }
    }

    private function log(
        int $actorId,
        string $action,
        string $status,
        ?int $archiveId,
        ?string $filename,
        string $summary
    ): void {
        DB::table('backup_logs')->insert([
            'actor_id' => $actorId,
            'action' => $action,
            'status' => $status,
            'archive_id' => $archiveId,
            'filename' => $filename,
            'summary' => $summary,
            'created_at' => now(),
        ]);
    }
}
