<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table): void {
            $table->boolean('is_alumni')->default(false);
            $table->index(['is_alumni', 'id_kelas', 'nama'], 'siswa_roster_idx');
            $table->index('induk', 'siswa_induk_idx');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table): void {
            $table->dropIndex('siswa_roster_idx');
            $table->dropIndex('siswa_induk_idx');
            $table->dropColumn('is_alumni');
        });
    }
};