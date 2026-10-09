<?php

namespace Tests\Feature;

use AbsenShalat\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_one_configured_account_for_each_role_idempotently(): void
    {
        $previous = config('initial_accounts');
        Config::set('initial_accounts', [
            'admin' => [
                'username' => 'seed-admin',
                'nama' => 'Admin Seeder',
                'email' => 'seed-admin@example.com',
                'password' => 'admin-password',
            ],
            'kesiswaan' => [
                'username' => 'seed-kesiswaan',
                'nama' => 'Kesiswaan Seeder',
                'password' => 'kesiswaan-password',
            ],
            'absensi' => [
                'username' => 'seed-absensi',
                'nama' => 'Absensi Seeder',
                'password' => 'absensi-password',
            ],
        ]);

        try {
            $this->seed(DatabaseSeeder::class);

            $this->assertDatabaseCount('users', 3);
            $this->assertDatabaseHas('users', [
                'username' => 'seed-admin',
                'nama' => 'Admin Seeder',
                'email' => 'seed-admin@example.com',
                'role' => 'admin',
            ]);
            $this->assertDatabaseHas('users', ['username' => 'seed-kesiswaan', 'role' => 'kesiswaan']);
            $this->assertDatabaseHas('users', ['username' => 'seed-absensi', 'role' => 'absensi']);
            $this->assertTrue(Hash::check('admin-password', User::query()->where('username', 'seed-admin')->value('password')));
            $this->assertTrue(Hash::check('kesiswaan-password', User::query()->where('username', 'seed-kesiswaan')->value('password')));
            $this->assertTrue(Hash::check('absensi-password', User::query()->where('username', 'seed-absensi')->value('password')));

            $this->seed(DatabaseSeeder::class);
            $this->assertDatabaseCount('users', 3);
        } finally {
            Config::set('initial_accounts', $previous);
        }
    }
}
