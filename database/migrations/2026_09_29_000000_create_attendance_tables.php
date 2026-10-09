<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kelas')) {
            Schema::create('kelas', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nama', 50)->unique();
            });
        }

        if (! Schema::hasTable('siswa')) {
            Schema::create('siswa', function (Blueprint $table) {
                $table->increments('id');
                $table->string('induk', 30)->nullable();
                $table->string('nama', 150);
                $table->enum('jenis_kelamin', ['L', 'P']);
                $table->boolean('is_irma')->default(false);
                $table->unsignedInteger('id_kelas');
                $table->unique(['nama', 'jenis_kelamin', 'id_kelas'], 'siswa_unik');
                $table->index('id_kelas', 'siswa_kelas');
                $table->foreign('id_kelas')->references('id')->on('kelas')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('absensi')) {
            Schema::create('absensi', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_siswa');
                $table->date('tanggal');
                $table->enum('status', ['H', 'A', 'I', 'S']);
                $table->unique(['id_siswa', 'tanggal'], 'absensi_unik');
                $table->foreign('id_siswa')->references('id')->on('siswa')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('kelas');
    }
};
