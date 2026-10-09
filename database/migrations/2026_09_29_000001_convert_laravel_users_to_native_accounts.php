<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $hasUsername = Schema::hasColumn('users', 'username');
        $hasName = Schema::hasColumn('users', 'name');
        $hasEmail = Schema::hasColumn('users', 'email');
        $hasNama = Schema::hasColumn('users', 'nama');
        $hasRole = Schema::hasColumn('users', 'role');

        Schema::table('users', function (Blueprint $table) use ($hasUsername, $hasNama, $hasRole): void {
            if (! $hasUsername) {
                $table->string('username', 50)->nullable()->unique();
            }
            if (! $hasNama) {
                $table->string('nama', 100)->nullable();
            }
            if (! $hasRole) {
                $table->enum('role', ['admin', 'kesiswaan', 'absensi'])->default('absensi');
            }
        });

        if (! $hasUsername || ! $hasNama) {
            $columns = ['id'];
            if (! $hasUsername && $hasEmail) {
                $columns[] = 'email';
            }
            if (! $hasNama && $hasName) {
                $columns[] = 'name';
            }

            foreach (DB::table('users')->select($columns)->orderBy('id')->get() as $user) {
                $updates = [];
                if (! $hasUsername) {
                    $email = (string) ($user->email ?? '');
                    $updates['username'] = $email !== '' && strlen($email) <= 50 ? $email : 'user'.$user->id;
                }
                if (! $hasNama) {
                    $updates['nama'] = (string) ($user->name ?? 'Pengguna');
                }
                DB::table('users')->where('id', $user->id)->update($updates);
            }
        }

        if ($hasEmail) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_email_unique');
                $table->dropColumn('email');
            });
        }
        if ($hasName) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('name'));
        }

        $obsoleteColumns = array_values(array_filter(
            ['email_verified_at', 'remember_token', 'updated_at'],
            fn (string $column): bool => Schema::hasColumn('users', $column)
        ));
        if ($obsoleteColumns !== []) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($obsoleteColumns));
        }
    }

    public function down(): void
    {
        // Native account records are retained in username/nama form on rollback.
    }
};
