<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_face_registrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('student_id')->unique();
            $table->longText('face_descriptor');
            $table->timestamps();
            $table->foreign('student_id')->references('id')->on('siswa')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_face_registrations');
    }
};
