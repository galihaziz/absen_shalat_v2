<?php

namespace Tests\Feature;

use AbsenShalat\Models\Attendance;
use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use AbsenShalat\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_page_and_creation_are_limited_to_admin_and_kesiswaan(): void
    {
        $admin = $this->makeUser('admin');
        $kesiswaan = $this->makeUser('kesiswaan');
        $absensi = $this->makeUser('absensi');

        $this->get(route('backups.index'))->assertRedirect(route('login'));
        $this->actingAs($absensi)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('backups.index'))->assertOk()->assertSee('Backup & Log');
        $this->actingAs($kesiswaan)->post(route('backups.store'))->assertRedirect(route('backups.index'));

        $this->assertDatabaseHas('backup_archives', ['created_by' => $kesiswaan->id]);
    }

    public function test_admin_can_restore_a_backup_and_system_keeps_a_safety_backup_and_audit_log(): void
    {
        Storage::fake('local');
        $admin = $this->makeUser('admin');
        $classroom = Classroom::query()->create(['nama' => 'X PPLG 1']);
        $student = Student::query()->create([
            'induk' => '44001',
            'nama' => 'Nama Sebelum Backup',
            'jenis_kelamin' => 'L',
            'id_kelas' => $classroom->id,
        ]);
        Attendance::query()->create([
            'id_siswa' => $student->id,
            'tanggal' => '2026-10-15',
            'status' => 'H',
        ]);

        $this->actingAs($admin)->post(route('backups.store'))->assertRedirect(route('backups.index'));
        $archive = DB::table('backup_archives')->first();
        $backupPath = 'private/backups/'.$archive->filename;
        $this->assertTrue(Storage::disk('local')->exists($backupPath));
        $this->assertStringNotContainsString(
            'Nama Sebelum Backup',
            Storage::disk('local')->get($backupPath),
            'Backup contents must be encrypted at rest.'
        );

        DB::table('siswa')->where('id', $student->id)->update(['nama' => 'Nama Setelah Backup']);
        DB::table('absensi')->where('id_siswa', $student->id)->delete();

        $this->post(route('backups.restore', $archive->id))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('siswa', ['id' => $student->id, 'nama' => 'Nama Sebelum Backup']);
        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $student->id,
            'tanggal' => '2026-10-15',
            'status' => 'H',
        ]);
        $this->assertSame(2, DB::table('backup_archives')->count());
        $this->assertDatabaseHas('backup_logs', ['action' => 'restore', 'status' => 'success']);
        $this->assertDatabaseHas('backup_logs', ['action' => 'pre_restore_backup', 'status' => 'success']);
    }

    public function test_kesiswaan_cannot_restore_a_backup(): void
    {
        Storage::fake('local');
        $kesiswaan = $this->makeUser('kesiswaan');
        $this->actingAs($kesiswaan)->post(route('backups.store'))->assertRedirect(route('backups.index'));
        $archiveId = DB::table('backup_archives')->value('id');

        $this->post(route('backups.restore', $archiveId))->assertForbidden();
        $this->assertDatabaseMissing('backup_logs', ['action' => 'restore']);
    }

    public function test_backup_schedule_can_be_configured_and_is_not_reset_when_page_is_reopened(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put(route('backups.schedule.update'), [
            'enabled' => '1',
            'interval_value' => 2,
            'interval_unit' => 'weeks',
        ])->assertRedirect(route('backups.index'))
            ->assertSessionHas('status');

        $schedule = DB::table('backup_schedule_settings')->where('id', 1)->first();
        $this->assertTrue((bool) $schedule->enabled);
        $this->assertSame(2, (int) $schedule->interval_value);
        $this->assertSame('weeks', $schedule->interval_unit);
        $this->assertEqualsWithDelta(14, now()->diffInDays($schedule->next_run_at), 0.01);

        $this->actingAs($admin)->get(route('backups.index'))->assertOk()
            ->assertSee('Backup otomatis')
            ->assertSee('Minggu');
        $this->assertSame('weeks', DB::table('backup_schedule_settings')->where('id', 1)->value('interval_unit'));
    }

    public function test_due_automatic_backup_is_created_once_and_rescheduled(): void
    {
        Storage::fake('local');
        $now = now();
        DB::table('backup_schedule_settings')->insert([
            'id' => 1,
            'enabled' => true,
            'interval_value' => 3,
            'interval_unit' => 'days',
            'next_run_at' => $now->copy()->subMinute(),
            'updated_by' => null,
            'updated_at' => $now,
        ]);

        Artisan::call('backups:run-scheduled');
        Artisan::call('backups:run-scheduled');

        $this->assertSame(1, DB::table('backup_archives')->count());
        $this->assertDatabaseHas('backup_archives', ['created_by' => null]);
        $this->assertDatabaseHas('backup_logs', ['action' => 'scheduled_backup', 'actor_id' => null, 'status' => 'success']);
        $this->assertEqualsWithDelta(
            3,
            now()->diffInDays(DB::table('backup_schedule_settings')->where('id', 1)->value('next_run_at')),
            0.01
        );
    }

    public function test_backup_schedule_rejects_invalid_intervals_and_can_be_disabled(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put(route('backups.schedule.update'), [
            'enabled' => '1',
            'interval_value' => 0,
            'interval_unit' => 'years',
        ])->assertSessionHasErrors(['interval_value', 'interval_unit']);

        $this->actingAs($admin)->put(route('backups.schedule.update'), [
            'enabled' => '0',
            'interval_value' => 1,
            'interval_unit' => 'months',
        ])->assertRedirect(route('backups.index'));

        $schedule = DB::table('backup_schedule_settings')->where('id', 1)->first();
        $this->assertFalse((bool) $schedule->enabled);
        $this->assertNull($schedule->next_run_at);
    }

    public function test_monthly_schedule_does_not_overflow_short_months(): void
    {
        Carbon::setTestNow('2026-01-31 10:00:00');

        try {
            $admin = $this->makeUser('admin');
            $this->actingAs($admin)->put(route('backups.schedule.update'), [
                'enabled' => '1',
                'interval_value' => 1,
                'interval_unit' => 'months',
            ])->assertRedirect(route('backups.index'));

            $this->assertSame(
                '2026-02-28 10:00:00',
                Carbon::parse(DB::table('backup_schedule_settings')->where('id', 1)->value('next_run_at'))
                    ->format('Y-m-d H:i:s')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    private function makeUser(string $role): User
    {
        return User::query()->create([
            'username' => $role.'-backup',
            'nama' => ucfirst($role).' Backup',
            'role' => $role,
            'password' => Hash::make('password123'),
        ]);
    }
}
