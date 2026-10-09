<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siswa', 'portal_pin')) {
            Schema::table('siswa', function (Blueprint $table): void {
                $table->string('portal_pin')->nullable();
            });
        }

        $defaultPinHash = Hash::make((string) config('app.student_default_pin', 'siswa123'));
        DB::table('siswa')->whereNull('portal_pin')->update(['portal_pin' => $defaultPinHash]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('siswa', 'portal_pin')) {
            Schema::table('siswa', function (Blueprint $table): void {
                $table->dropColumn('portal_pin');
            });
        }
    }
};
