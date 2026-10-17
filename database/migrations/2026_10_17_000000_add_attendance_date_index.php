<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'absensi_tanggal_index';

    public function up(): void
    {
        if (Schema::hasTable('absensi') && ! Schema::hasIndex('absensi', self::INDEX)) {
            Schema::table('absensi', function (Blueprint $table): void {
                $table->index('tanggal', self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('absensi') && Schema::hasIndex('absensi', self::INDEX)) {
            Schema::table('absensi', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
