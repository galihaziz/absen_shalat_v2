<?php

namespace Tests\Feature;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use AbsenShalat\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StaffRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_register_with_username_and_confirmed_password_and_wait_for_approval(): void
    {
        $this->get(route('staff.register'))
            ->assertOk()
            ->assertSee('Konfirmasi password')
            ->assertSee('Email / Gmail')
            ->assertDontSee('Google');

        $this->post(route('staff.register.store'), [
            'nama' => 'Petugas Baru',
            'username' => 'petugas-baru',
            'email' => 'petugas@example.test',
            'password' => 'password-aman',
            'password_confirmation' => 'password-berbeda',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);

        $this->followingRedirects()->post(route('staff.register.store'), [
            'nama' => 'Petugas Baru',
            'username' => 'petugas-baru',
            'email' => 'petugas@example.test',
            'password' => 'password-aman',
            'password_confirmation' => 'password-aman',
        ])->assertOk()
            ->assertSee('Pendaftaran berhasil', false)
            ->assertSee('tunggu konfirmasi admin')
            ->assertSee('role="alertdialog"', false)
            ->assertSee('Oke, mengerti');

        $user = User::query()->where('username', 'petugas-baru')->firstOrFail();
        $this->assertSame('absensi', $user->role);
        $this->assertSame('pending', $user->approval_status);
        $this->assertSame('petugas@example.test', $user->email);
        $this->assertTrue(Hash::check('password-aman', $user->password));
        $this->assertFalse(Auth::check());

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password-aman',
        ])->assertSessionHasErrors('username');

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Pendaftaran menunggu persetujuan')
            ->assertSee($user->username);
        $this->post(route('users.approve', $user))->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'approval_status' => 'approved',
            'approved_by' => $admin->id,
        ]);

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password-aman',
        ])->assertRedirect(route('attendance.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_duplicate_usernames_and_passwords_without_confirmation(): void
    {
        $this->makeUser('absensi', 'nama-sama');

        $this->post(route('staff.register.store'), [
            'nama' => 'Petugas',
            'username' => 'nama-sama',
            'email' => 'petugas@example.test',
            'password' => 'password-aman',
            'password_confirmation' => 'password-aman',
        ])->assertSessionHasErrors('username');

        $this->post(route('staff.register.store'), [
            'nama' => 'Petugas',
            'username' => 'nama-baru',
            'email' => 'petugas@example.test',
            'password' => 'password-aman',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_duplicate_or_invalid_email_addresses(): void
    {
        $this->makeUser('absensi', 'akun-lama', 'petugas@example.test');

        $payload = [
            'nama' => 'Petugas Baru',
            'username' => 'petugas-baru',
            'email' => 'petugas@example.test',
            'password' => 'password-aman',
            'password_confirmation' => 'password-aman',
        ];
        $this->post(route('staff.register.store'), $payload)->assertSessionHasErrors('email');

        $payload['email'] = 'bukan-email';
        $this->post(route('staff.register.store'), $payload)->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_account_crud_requires_matching_password_confirmation_for_every_role(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        foreach (['admin', 'kesiswaan', 'absensi'] as $role) {
            $username = 'crud-'.$role;
            $payload = [
                'username' => $username,
                'nama' => 'Akun '.ucfirst($role),
                'role' => $role,
                'email' => $role === 'admin' ? 'crud-admin@example.test' : 'crud-'.$role.'@example.test',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ];
            if ($role === 'admin') {
                $payload['email'] = 'crud-admin@example.test';
            }

            $this->post(route('users.store'), $payload)->assertRedirect(route('users.index'));
            $created = User::query()->where('username', $username)->firstOrFail();
            $this->assertSame($role, $created->role);
            $this->assertTrue(Hash::check('password-baru', $created->password));

            $this->post(route('users.store'), array_merge($payload, [
                'id' => $created->id,
                'password' => 'password-lain',
                'password_confirmation' => 'tidak-cocok',
            ]))->assertSessionHasErrors('password');
        }

        $this->assertDatabaseCount('users', 4);
    }

    public function test_google_oauth_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('google.redirect'));
        $this->assertFalse(Route::has('google.callback'));
    }

    public function test_qr_and_manual_attendance_keep_the_staff_operator_name(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(8, 0));
        $operator = $this->makeUser('admin');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '123456',
            'nama' => 'Siswa Uji',
            'jenis_kelamin' => 'L',
            'id_kelas' => $classroom->id,
        ]);

        $this->actingAs($operator)->postJson(route('attendance.scan.record'), ['induk' => $student->induk])
            ->assertOk()->assertJsonPath('operator', $operator->nama);
        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $student->id,
            'tanggal' => now()->toDateString(),
            'recorded_by_user_id' => $operator->id,
        ]);

        $this->get(route('attendance.index', [
            'kelas' => $classroom->nama,
            'bulan' => now()->month,
            'tahun' => now()->year,
        ]))->assertOk()->assertSee('Oleh: '.$operator->nama);

        $manualDate = now()->addDay()->toDateString();
        $this->postJson(route('attendance.save'), [
            'id_siswa' => $student->id,
            'tanggal' => $manualDate,
            'status' => 'I',
        ])->assertOk()->assertJsonPath('operator', $operator->nama);
        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $student->id,
            'tanggal' => $manualDate,
            'recorded_by_user_id' => $operator->id,
        ]);
    }

    private function makeUser(string $role, string $username = '', ?string $email = null): User
    {
        return User::query()->create([
            'username' => $username !== '' ? $username : $role.'-'.uniqid(),
            'nama' => 'Admin Absensi',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);
    }
}
