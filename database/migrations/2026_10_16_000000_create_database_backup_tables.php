<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('filename')->unique();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('record_count')->default(0);
            $table->timestamp('created_at');
        });

        Schema::create('backup_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 40);
            $table->string('status', 20);
            $table->unsignedBigInteger('archive_id')->nullable()->index();
            $table->string('filename')->nullable();
            $table->string('summary');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('backup_archives');
    }
};
