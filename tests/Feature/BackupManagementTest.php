<?php

namespace Tests\Feature;

use AbsenShalat\Models\Attendance;
use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use AbsenShalat\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
