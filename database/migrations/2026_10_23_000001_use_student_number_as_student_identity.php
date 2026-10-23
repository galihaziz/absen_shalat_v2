<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateInduk = DB::table('siswa')
            ->select('induk')
            ->whereNotNull('induk')
            ->groupBy('induk')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->pluck('induk');

        if ($duplicateInduk->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak dapat mengubah kunci unik siswa karena nomor induk ganda ditemukan: '.$duplicateInduk->implode(', ').'. Perbaiki data tersebut lalu jalankan migrasi kembali.'
            );
        }

        foreach (Schema::getIndexes('siswa') as $index) {
            if ($index['unique'] && $index['columns'] === ['nama', 'jenis_kelamin', 'id_kelas']) {
                Schema::table('siswa', static function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }

        if (! $this->hasUniqueIndexOn(['induk'])) {
            Schema::table('siswa', function (Blueprint $table): void {
                $table->unique('induk', 'siswa_induk_unique');
            });
        }
    }

    public function down(): void
    {
        $duplicateIdentities = DB::table('siswa')
            ->select(['nama', 'jenis_kelamin', 'id_kelas'])
            ->groupBy('nama', 'jenis_kelamin', 'id_kelas')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get()
            ->map(static fn (object $student): string => "{$student->nama} ({$student->jenis_kelamin}, kelas {$student->id_kelas})");

        if ($duplicateIdentities->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak dapat mengembalikan batasan nama lama karena nama siswa kembar ditemukan: '.$duplicateIdentities->implode(', ').'.'
            );
        }

        foreach (Schema::getIndexes('siswa') as $index) {
            if ($index['name'] === 'siswa_induk_unique') {
                Schema::table('siswa', static function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }

        if (! $this->hasUniqueIndexOn(['nama', 'jenis_kelamin', 'id_kelas'])) {
            Schema::table('siswa', function (Blueprint $table): void {
                $table->unique(['nama', 'jenis_kelamin', 'id_kelas'], 'siswa_unik');
            });
        }
    }

    private function hasUniqueIndexOn(array $columns): bool
    {
        foreach (Schema::getIndexes('siswa') as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }
};
