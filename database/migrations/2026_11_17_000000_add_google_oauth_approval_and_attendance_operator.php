<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('approval_status', 20)->default('approved')->index();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('absensi', function (Blueprint $table): void {
            $table->unsignedInteger('recorded_by_user_id')->nullable()->index();
            $table->foreign('recorded_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table): void {
            $table->dropForeign(['recorded_by_user_id']);
            $table->dropIndex(['recorded_by_user_id']);
            $table->dropColumn('recorded_by_user_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn(['approval_status', 'approved_at', 'approved_by']);
        });
    }
};
