<?php

namespace Database\Seeders;

use AbsenShalat\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $settings = config('initial_accounts');
        $accounts = [
            [
                'username' => $settings['admin']['username'] ?? null,
                'nama' => $settings['admin']['nama'] ?? null,
                'email' => $settings['admin']['email'] ?? null,
                'password' => $settings['admin']['password'] ?? null,
                'role' => 'admin',
            ],
            [
                'username' => $settings['kesiswaan']['username'] ?? null,
                'nama' => $settings['kesiswaan']['nama'] ?? null,
                'email' => null,
                'password' => $settings['kesiswaan']['password'] ?? null,
                'role' => 'kesiswaan',
            ],
            [
                'username' => $settings['absensi']['username'] ?? null,
                'nama' => $settings['absensi']['nama'] ?? null,
                'email' => null,
                'password' => $settings['absensi']['password'] ?? null,
                'role' => 'absensi',
            ],
        ];

        $missing = [];
        foreach ($accounts as $account) {
            foreach (['username', 'nama', 'password'] as $field) {
                if (! is_string($account[$field]) || trim($account[$field]) === '') {
                    $envField = $field === 'nama' ? 'NAME' : strtoupper($field);
                    $missing[] = 'APP_INITIAL_'.strtoupper($account['role']).'_'.$envField;
                }
            }
        }

        if (! is_string($accounts[0]['email']) || filter_var($accounts[0]['email'], FILTER_VALIDATE_EMAIL) === false) {
            $missing[] = 'APP_INITIAL_ADMIN_EMAIL (alamat email admin yang valid)';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Lengkapi konfigurasi akun seeder di .env: '.implode(', ', $missing).'.'
            );
        }

        $usernames = array_column($accounts, 'username');
        if (count(array_unique($usernames)) !== count($usernames)) {
            throw new RuntimeException('Username akun admin, kesiswaan, dan absensi harus berbeda.');
        }

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['username' => $account['username']],
                [
                    'nama' => $account['nama'],
                    'email' => $account['email'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                ]
            );
        }
    }
}
