<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table): void {
            $table->enum('status', ['H', 'A', 'I', 'S', 'M'])->change();
        });
    }

    public function down(): void
    {
        if (DB::table('absensi')->where('status', 'M')->exists()) {
            throw new RuntimeException('Cannot remove the Haid attendance status while Haid records still exist.');
        }

        Schema::table('absensi', function (Blueprint $table): void {
            $table->enum('status', ['H', 'A', 'I', 'S'])->change();
        });
    }
};
