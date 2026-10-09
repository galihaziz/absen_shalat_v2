<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyEndTime = DB::table('app_settings')->where('key', 'attendance_scan_cutoff')->value('value');
        if ($legacyEndTime === null) {
            return;
        }

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'attendance_scan_start'],
            ['value' => '00:00']
        );
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'attendance_scan_end'],
            ['value' => $legacyEndTime]
        );
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', ['attendance_scan_start', 'attendance_scan_end'])->delete();
    }
};