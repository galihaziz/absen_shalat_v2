<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_face_registrations', function (Blueprint $table): void {
            $table->string('face_code', 36)->nullable()->unique();
        });

        DB::table('student_face_registrations')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($registrations): void {
                foreach ($registrations as $registration) {
                    DB::table('student_face_registrations')
                        ->where('id', $registration->id)
                        ->update(['face_code' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('student_face_registrations', function (Blueprint $table): void {
            $table->dropUnique(['face_code']);
            $table->dropColumn('face_code');
        });
    }
};
