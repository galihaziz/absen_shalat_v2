<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siswa', 'is_nonis')) {
            Schema::table('siswa', function (Blueprint $table): void {
                $table->boolean('is_nonis')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('siswa', 'is_nonis')) {
            Schema::table('siswa', function (Blueprint $table): void {
                $table->dropColumn('is_nonis');
            });
        }
    }
};
