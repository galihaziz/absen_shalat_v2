<?php

namespace Tests\Feature;

use AbsenShalat\Models\Attendance;
use AbsenShalat\Models\AppSetting;
use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use AbsenShalat\Models\User;
use App\Services\StudentCardPhotoProcessor;
use App\Services\StudentQrCodeGenerator;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class NativeFlowMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_native_username_and_role_fields(): void
    {
        User::query()->create([
            'username' => 'admin',
            'nama' => 'Administrator',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', ['username' => 'admin', 'password' => 'password123'])
            ->assertRedirect('/staff');
        $this->get('/staff')->assertOk();
        $this->assertSame(120, (int) config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));

        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/staff')->assertRedirect('/staff/login');
    }

    public function test_root_always_shows_student_login_and_staff_login_is_explicit(): void
    {
        config(['app.staff_domain' => 'staff.example.test', 'app.student_domain' => 'siswa.example.test']);
        config(['app.env' => 'production']);

        $classroom = Classroom::query()->create(['nama' => 'X PPLG 1']);
        $student = Student::query()->create([
            'induk' => '901234', 'nama' => 'Siswa Domain', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);

        $this->get('http://siswa.example.test/')->assertOk()
            ->assertSee('action="http://siswa.example.test"', false)->assertSee('Nomor induk')->assertSee('PIN');
        $this->get('http://siswa.example.test/siswa/login')->assertRedirect('http://siswa.example.test');
        $this->post('http://siswa.example.test/', ['induk' => $student->induk, 'pin' => 'siswa123'])
            ->assertRedirect('http://siswa.example.test/siswa/kartu');
        $this->get('http://tunnel.ngrok-free.app/')->assertOk()
            ->assertSee('action="http://tunnel.ngrok-free.app"', false)->assertSee('Nomor induk')->assertSee('PIN');
        $this->post('http://tunnel.ngrok-free.app/', ['induk' => $student->induk, 'pin' => 'siswa123'])
            ->assertRedirect('http://tunnel.ngrok-free.app/siswa/kartu');
        $this->get('http://staff.example.test/')->assertOk()
            ->assertSee('action="http://staff.example.test"', false)->assertSee('Nomor induk')->assertSee('PIN');
        $this->get('http://staff.example.test/staff/login')->assertOk();

        foreach (['admin', 'kesiswaan', 'absensi'] as $role) {
            $this->actingAs($this->makeUser($role))->get('http://staff.example.test/staff')->assertOk();
        }
    }

    public function test_staff_login_path_is_canonical_and_legacy_login_remains_available(): void
    {
        $this->get('/staff/login')->assertOk()->assertSee(route('login.store'), false);
        $this->get('/login')->assertOk();
    }

    public function test_staff_login_form_uses_https_when_behind_an_https_proxy(): void
    {
        $this->get('http://staff.example.test/staff/login', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'staff.example.test',
        ])->assertOk()->assertSee('action="https://staff.example.test/staff/login"', false);
    }

    public function test_all_authenticated_roles_can_open_qr_scanner(): void
    {
        foreach (['admin', 'kesiswaan', 'absensi'] as $role) {
            $this->actingAs($this->makeUser($role))->get('/scan-qr')->assertOk()->assertSee('Scan QR');
        }
    }

    public function test_scanned_induk_marks_student_present_idempotently(): void
    {
        $user = $this->makeUser('absensi');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '900123', 'nama' => 'Siswa Scan QR', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        $today = now()->toDateString();
        Attendance::query()->create(['id_siswa' => $student->id, 'tanggal' => $today, 'status' => 'A']);

        $firstScan = $this->actingAs($user)->postJson('/scan-qr', ['induk' => $student->induk])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('already_present', false)
            ->assertJsonPath('student.nama', 'Siswa Scan QR');
        $firstScanTimeResponse = $firstScan->json('waktu_scan');
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $firstScanTimeResponse);
        $firstScanTime = Attendance::query()->where('id_siswa', $student->id)->where('tanggal', $today)->value('waktu_scan')->format('Y-m-d H:i:s');
        $this->postJson('/scan-qr', ['induk' => $student->induk])
            ->assertOk()->assertJsonPath('already_present', true)->assertJsonPath('waktu_scan', $firstScanTimeResponse);

        $this->assertSame(1, Attendance::query()->where('id_siswa', $student->id)->where('tanggal', $today)->count());
        $this->assertDatabaseHas('absensi', ['id_siswa' => $student->id, 'tanggal' => $today, 'status' => 'H']);
        $this->assertSame(
            $firstScanTime,
            Attendance::query()->where('id_siswa', $student->id)->where('tanggal', $today)->value('waktu_scan')->format('Y-m-d H:i:s')
        );
        $this->postJson('/scan-qr', ['induk' => '999999'])->assertNotFound();
        $this->postJson('/scan-qr', ['induk' => 'not-numeric'])->assertUnprocessable();
    }

    public function test_haid_qr_records_haid_for_female_students_and_rejects_male_students(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $femaleStudent = Student::query()->create([
            'induk' => '900126', 'nama' => 'Siswi Scan Haid', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        $maleStudent = Student::query()->create([
            'induk' => '900127', 'nama' => 'Siswa Scan Haid', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        $user = $this->makeUser('absensi');

        $this->actingAs($user)->postJson('/scan-qr', ['induk' => $maleStudent->induk, 'jenis' => 'haid'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'QR haid hanya berlaku untuk siswi.');
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $maleStudent->id, 'tanggal' => now()->toDateString()]);

        $this->postJson('/scan-qr', ['induk' => $femaleStudent->induk, 'jenis' => 'haid'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_recorded', false)
            ->assertJsonPath('keterangan', 'M')
            ->assertJsonPath('message', 'Keterangan: M berhasil dicatat.');
        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $femaleStudent->id,
            'tanggal' => now()->toDateString(),
            'status' => 'M',
        ]);

        $this->postJson('/scan-qr', ['induk' => $femaleStudent->induk, 'jenis' => 'haid'])
            ->assertOk()
            ->assertJsonPath('already_recorded', true)
            ->assertJsonPath('message', 'Keterangan M sudah tercatat hari ini.');
        $this->assertSame(1, Attendance::query()->where('id_siswa', $femaleStudent->id)->where('tanggal', now()->toDateString())->count());
    }

    public function test_manual_haid_status_is_only_allowed_for_female_students(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $maleStudent = Student::query()->create([
            'induk' => '900128', 'nama' => 'Siswa Status Haid', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        $femaleStudent = Student::query()->create([
            'induk' => '900129', 'nama' => 'Siswi Status Haid', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        $user = $this->makeUser('absensi');
        $date = now()->toDateString();

        $this->actingAs($user)->postJson('/proses_absen', [
            'id_siswa' => $maleStudent->id, 'tanggal' => $date, 'status' => 'M',
        ])->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $maleStudent->id, 'tanggal' => $date]);

        $this->postJson('/proses_absen', [
            'id_siswa' => $femaleStudent->id, 'tanggal' => $date, 'status' => 'M',
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('absensi', ['id_siswa' => $femaleStudent->id, 'tanggal' => $date, 'status' => 'M']);
    }

    public function test_qr_scan_page_shows_today_without_manual_induk_input(): void
    {
        $this->actingAs($this->makeUser('absensi'))
            ->get('/scan-qr')->assertOk()->assertSee(now()->format('d/m/Y'))
            ->assertSee('QR Umum')->assertSee('QR Haid')
            ->assertDontSee('name="induk"', false)->assertDontSee('Catat Hadir');
    }

    public function test_scan_after_configured_window_is_rejected(): void
    {
        Carbon::setTestNow('2026-10-01 08:31:00');
        try {
            $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
            $student = Student::query()->create([
                'induk' => '900124', 'nama' => 'Siswa Terlambat', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
            ]);
            AppSetting::query()->create(['key' => AppSetting::ATTENDANCE_SCAN_START, 'value' => '07:00']);
            AppSetting::query()->create(['key' => AppSetting::ATTENDANCE_SCAN_END, 'value' => '08:30']);

            $this->actingAs($this->makeUser('absensi'))->postJson('/scan-qr', ['induk' => $student->induk])
                ->assertForbidden()->assertJsonPath('success', false)->assertJsonPath('message', 'Scan QR hanya dibuka pukul 07:00 sampai 08:30.');
            $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id, 'tanggal' => '2026-10-01']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_scan_at_end_of_configured_window_is_allowed(): void
    {
        Carbon::setTestNow('2026-10-01 08:30:59');
        try {
            $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
            $student = Student::query()->create([
                'induk' => '900125', 'nama' => 'Siswa Tepat Waktu', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
            ]);
            AppSetting::query()->create(['key' => AppSetting::ATTENDANCE_SCAN_START, 'value' => '07:00']);
            AppSetting::query()->create(['key' => AppSetting::ATTENDANCE_SCAN_END, 'value' => '08:30']);

            $this->actingAs($this->makeUser('absensi'))->postJson('/scan-qr', ['induk' => $student->induk])
                ->assertOk()->assertJsonPath('success', true);
            $this->assertDatabaseHas('absensi', ['id_siswa' => $student->id, 'tanggal' => '2026-10-01', 'status' => 'H']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_only_admin_and_kesiswaan_can_configure_scan_window(): void
    {
        $this->actingAs($this->makeUser('admin'))->post('/laporan/batas-scan', ['scan_start' => '07:00', 'scan_end' => '08:30'])
            ->assertRedirect(route('reports.index'));
        $this->actingAs($this->makeUser('kesiswaan'))->get('/laporan')
            ->assertOk()->assertSee('Jam operasional scan QR')->assertSee('07:00')->assertSee('08:30');
        $this->actingAs($this->makeUser('absensi'))->post('/laporan/batas-scan', ['scan_start' => '07:00', 'scan_end' => '09:00'])
            ->assertForbidden();
    }

    public function test_attendance_saves_are_upserts_and_roles_are_enforced(): void
    {
        $user = $this->makeUser('absensi');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '1234', 'nama' => 'Siswa Uji', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);

        $this->actingAs($user)->postJson('/proses_absen', [
            'id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'H',
        ])->assertOk()->assertJson(['success' => true]);
        $this->postJson('/proses_absen', [
            'id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'A',
        ])->assertOk();

        $this->assertSame(1, Attendance::query()->count());
        $this->assertDatabaseHas('absensi', ['id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'A']);
        $this->get('/staff?bulan=9&tahun=2026&kelas=X%20IPA%201')
            ->assertOk()->assertSee('REKAP MINGGUAN')->assertSee('TOTAL BULANAN')->assertSee('Siswa Uji')->assertSee('Keterangan: - = Belum diisi, H = Hadir');
        $this->get('/data_siswa')->assertForbidden();
    }

    public function test_unmarked_attendance_stays_blank_until_h_is_selected(): void
    {
        $user = $this->makeUser('absensi');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '1235', 'nama' => 'Siswa Belum Diabsen', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        $page = $this->actingAs($user)->get('/staff?bulan=9&tahun=2026&kelas=X%20IPA%201')
            ->assertOk()->assertSee('value="" selected>-</option>', false)
            ->assertDontSee('value="H" selected', false);
        $this->assertSame(4, substr_count($page->getContent(), '<td class="total">0</td>'));
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id, 'tanggal' => '2026-09-28']);

        $payload = ['id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => ''];
        $this->postJson('/proses_absen', $payload)->assertOk();
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id, 'tanggal' => '2026-09-28']);

        $payload['status'] = 'H';
        $this->postJson('/proses_absen', $payload)->assertOk();
        $this->assertDatabaseHas('absensi', ['id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'H']);

        $payload['status'] = '';
        $this->postJson('/proses_absen', $payload)->assertOk();
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id, 'tanggal' => '2026-09-28']);
    }

    public function test_attendance_and_report_tables_show_qr_scan_times(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'X PPLG 1']);
        $student = Student::query()->create([
            'induk' => '1236', 'nama' => 'Siswa Scan Bertanggal', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        Attendance::query()->create([
            'id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'H', 'waktu_scan' => '2026-09-28 08:14:22',
        ]);
        Attendance::query()->create([
            'id_siswa' => $student->id, 'tanggal' => '2026-09-29', 'status' => 'H', 'waktu_scan' => '2026-09-29 09:10:11',
        ]);

        $this->actingAs($this->makeUser('absensi'))
            ->get('/staff?bulan=9&tahun=2026&kelas=X%20PPLG%201')
            ->assertOk()->assertSee('08:14:22')->assertSee('09:10:11');
        $this->actingAs($this->makeUser('kesiswaan'))
            ->get('/laporan?periode=minggu&minggu=2026-09-28')
            ->assertOk()->assertSee('Scan hadir terakhir')->assertSee('29/09/2026 09:10:11');
    }

    public function test_attendance_page_shows_today_counts_and_class_progress(): void
    {
        Carbon::setTestNow('2026-10-07 10:15:00');
        try {
            $classroom = Classroom::query()->create(['nama' => 'X PPLG 1']);
            $otherClassroom = Classroom::query()->create(['nama' => 'XI TJKT 1']);
            $scannedStudent = Student::query()->create([
                'induk' => '8100001', 'nama' => 'Sudah Scan', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
            ]);
            Student::query()->create([
                'induk' => '8100002', 'nama' => 'Belum Scan', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
            ]);
            Student::query()->create([
                'induk' => '8100003', 'nama' => 'Kelas Lain', 'jenis_kelamin' => 'L', 'id_kelas' => $otherClassroom->id,
            ]);
            Attendance::query()->create([
                'id_siswa' => $scannedStudent->id, 'tanggal' => '2026-10-07', 'status' => 'H', 'waktu_scan' => '2026-10-07 08:12:00',
            ]);

            $this->actingAs($this->makeUser('absensi'))
                ->get('/staff?bulan=10&tahun=2026&kelas=X%20PPLG%201')
                ->assertOk()
                ->assertSee('Ringkasan hari ini')
                ->assertSee('Belum diisi')
                ->assertSee('Progress absensi per kelas')
                ->assertSee('Tampilkan')
                ->assertSee('Sembunyikan')
                ->assertSee('Semua tingkat')
                ->assertSee('Semua jurusan')
                ->assertSee('data-grade="X" data-major="PPLG/RPL"', false)
                ->assertSee('1/2')
                ->assertSee('Scan terakhir 08:12')
                ->assertSee('Lanjutkan X PPLG 1')
                ->assertSee('Scan QR');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_report_shows_daily_attendance_trend_against_previous_period(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '8200001', 'nama' => 'Tren Hadir', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        foreach (['2026-09-28', '2026-10-05', '2026-10-06'] as $date) {
            Attendance::query()->create(['id_siswa' => $student->id, 'tanggal' => $date, 'status' => 'H']);
        }

        $this->actingAs($this->makeUser('kesiswaan'))
            ->get('/laporan?periode=minggu&minggu=2026-10-07')
            ->assertOk()
            ->assertSee('PERBANDINGAN TREN')
            ->assertSee('Periode ini (')
            ->assertSee('05/10/2026–07/10/2026')
            ->assertSee('28/09/2026–30/09/2026')
            ->assertSee('Naik 100% per hari');
    }

    public function test_mass_student_delete_by_class_grade_and_major_removes_attendance_and_photos(): void
    {
        Storage::fake('local');
        $pplgClass = Classroom::query()->create(['nama' => 'X PPLG 1']);
        $tjktClass = Classroom::query()->create(['nama' => 'X TJKT 1']);
        $rplClass = Classroom::query()->create(['nama' => 'XI RPL 1']);
        $tsmClass = Classroom::query()->create(['nama' => 'XII TSM 1']);
        $psBrClass = Classroom::query()->create(['nama' => 'XI PS BR 1']);
        $psBdClass = Classroom::query()->create(['nama' => 'XII PS BD 1']);
        $brClass = Classroom::query()->create(['nama' => 'XI BR 1']);
        $bdClass = Classroom::query()->create(['nama' => 'XII BD 1']);
        $ipaXiClass = Classroom::query()->create(['nama' => 'XI IPA 1']);
        $ipaXiiClass = Classroom::query()->create(['nama' => 'XII IPA 1']);
        $createStudent = static function (int $number, Classroom $classroom, bool $alumni = false): Student {
            $induk = (string) (8500000 + $number);
            $student = Student::query()->create([
                'induk' => $induk,
                'nama' => 'Hapus Massal '.$number,
                'jenis_kelamin' => 'L',
                'id_kelas' => $classroom->id,
                'is_alumni' => $alumni,
            ]);
            Attendance::query()->create([
                'id_siswa' => $student->id, 'tanggal' => '2026-10-07', 'status' => 'H',
            ]);
            Storage::disk('local')->put('foto-siswa/'.substr($induk, 0, 3).'/'.$induk.'.jpg', 'foto-uji');

            return $student;
        };
        $classStudent = $createStudent(1, $pplgClass);
        $alumniStudent = $createStudent(2, $pplgClass, true);
        $gradeStudent = $createStudent(3, $tjktClass);
        $majorStudent = $createStudent(4, $rplClass);
        $remainingStudent = $createStudent(5, $tsmClass);
        $ipaXiStudent = $createStudent(6, $ipaXiClass);
        $ipaXiiStudent = $createStudent(7, $ipaXiiClass);
        $psBrStudent = $createStudent(8, $psBrClass);
        $psBdStudent = $createStudent(9, $psBdClass);
        $brStudent = $createStudent(10, $brClass);
        $bdStudent = $createStudent(11, $bdClass);

        $this->actingAs($this->makeUser('admin'));
        $this->get('/data_siswa')->assertOk()
            ->assertSee('Hapus data siswa massal')
            ->assertSee('Termasuk siswa alumni')
            ->assertSee('IPA · 2 siswa')
            ->assertSee('PS/BR/BD · 4 siswa')
            ->assertSee('data-mass-delete-form', false);

        $this->delete('/data_siswa/hapus-massal', [
            'scope' => 'class', 'classroom_id' => $pplgClass->id, 'expected_count' => 2,
        ])->assertRedirect('/data_siswa');
        foreach ([$classStudent, $alumniStudent] as $student) {
            $this->assertDatabaseMissing('siswa', ['id' => $student->id]);
            $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id]);
            $this->assertFalse(Storage::disk('local')->exists('foto-siswa/'.substr($student->induk, 0, 3).'/'.$student->induk.'.jpg'));
        }

        $this->delete('/data_siswa/hapus-massal', [
            'scope' => 'grade', 'grade' => 'X', 'expected_count' => 99,
        ])->assertSessionHasErrors('scope');
        $this->assertDatabaseHas('siswa', ['id' => $gradeStudent->id]);

        $this->delete('/data_siswa/hapus-massal', [
            'scope' => 'grade', 'grade' => 'X', 'expected_count' => 1,
        ])->assertRedirect('/data_siswa');
        $this->assertDatabaseMissing('siswa', ['id' => $gradeStudent->id]);
        $this->assertFalse(Storage::disk('local')->exists('foto-siswa/'.substr($gradeStudent->induk, 0, 3).'/'.$gradeStudent->induk.'.jpg'));

        $this->delete('/data_siswa/hapus-massal', [
            'scope' => 'major', 'major' => 'PPLG/RPL', 'expected_count' => 1,
        ])->assertRedirect('/data_siswa');
        $this->assertDatabaseMissing('siswa', ['id' => $majorStudent->id]);
        $this->assertDatabaseHas('siswa', ['id' => $remainingStudent->id]);
        $this->assertDatabaseHas('absensi', ['id_siswa' => $remainingStudent->id]);
        $this->assertTrue(Storage::disk('local')->exists('foto-siswa/'.substr($remainingStudent->induk, 0, 3).'/'.$remainingStudent->induk.'.jpg'));

        $this->delete('/data_siswa/hapus-massal', [
            'scope' => 'major', 'major' => 'PS/BR/BD', 'expected_count' => 4,
        ])->assertRedirect('/data_siswa');
        foreach ([$psBrStudent, $psBdStudent, $brStudent, $bdStudent] as $student) {
            $this->assertDatabaseMissing('siswa', ['id' => $student->id]);
            $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id]);
        }

        $this->delete('/data_siswa/hapus-massal', [
            'scope' => 'major', 'major' => 'IPA', 'expected_count' => 2,
        ])->assertRedirect('/data_siswa');
        foreach ([$ipaXiStudent, $ipaXiiStudent] as $student) {
            $this->assertDatabaseMissing('siswa', ['id' => $student->id]);
            $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id]);
            $this->assertFalse(Storage::disk('local')->exists('foto-siswa/'.substr($student->induk, 0, 3).'/'.$student->induk.'.jpg'));
        }
    }

    public function test_alpha_report_works_on_sqlite_and_lists_absence_dates(): void
    {
        $user = $this->makeUser('kesiswaan');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '5678', 'nama' => 'Alpha Uji', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        Attendance::query()->create(['id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'A']);

        $this->actingAs($user)->get('/alpha?periode=minggu&minggu=2026-09-28')
            ->assertOk()->assertSee('Alpha Uji')->assertSee('28/09')->assertSee('Total alpha');
        $this->get('/laporan?periode=minggu&minggu=2026-09-28')
            ->assertOk()->assertSee('Rekap siswa')->assertSee('Alpha Uji')->assertSee('Grafik kehadiran')->assertSee('Ekspor Excel')->assertSee('Alpha terbanyak per rombel');
    }

    public function test_report_month_filters_show_month_names(): void
    {
        $this->actingAs($this->makeUser('kesiswaan'));

        foreach (['/laporan?bulan=9&tahun=2026', '/alpha?bulan=9&tahun=2026'] as $path) {
            $this->get($path)->assertOk()->assertSee('<option value="9" selected>September</option>', false);
        }
    }

    public function test_monthly_attendance_delete_form_is_on_report_page(): void
    {
        $this->actingAs($this->makeUser('admin'));
        Classroom::query()->create(['nama' => 'X IPA 1']);

        $this->get('/staff')->assertOk()->assertDontSee('Hapus catatan bulanan');
        $this->get('/laporan?bulan=9&tahun=2026&kelas=X%20IPA%201')
            ->assertOk()->assertSee('Hapus catatan bulanan')->assertSee('data-attendance-delete', false)
            ->assertSee('<option value="X IPA 1" selected>X IPA 1</option>', false);
    }

    public function test_admin_can_upload_use_and_remove_student_card_template(): void
    {
        Storage::fake('local');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/template_kartu')->assertOk()->assertSee('Belum ada template');
        $this->post('/template_kartu', ['template' => UploadedFile::fake()->image('background.png', 600, 1000)])
            ->assertRedirect(route('student-card-templates.index'));

        $templatePath = 'template-kartu/active.png';
        $this->assertTrue(Storage::disk('local')->exists($templatePath));
        $this->get('/template_kartu')->assertOk()->assertSee('Aktif')->assertSee('data:image/png;base64,', false);

        $classroom = Classroom::query()->create(['nama' => 'X PPLG 1']);
        $student = Student::query()->create([
            'induk' => '7654301', 'nama' => 'Template Uji', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        $this->get('/data_siswa/'.$student->id.'/qr')
            ->assertSee('qr-student-card-template')->assertSee('height: 85.6mm;', false);
        $pdf = $this->get('/data_siswa/'.$student->id.'/qr.pdf')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        preg_match('/\/MediaBox\s*\[0\.000 0\.000 ([\d.]+) ([\d.]+)\]/', $pdf->getContent(), $pageSize);
        $this->assertEqualsWithDelta(53.98 * 72 / 25.4, (float) $pageSize[1], 0.1);
        $this->assertEqualsWithDelta(85.6 * 72 / 25.4, (float) $pageSize[2], 0.1);

        $this->delete('/template_kartu')->assertRedirect(route('student-card-templates.index'));
        $this->assertFalse(Storage::disk('local')->exists($templatePath));
        $this->get('/template_kartu')->assertOk()->assertSee('Belum ada template');
    }

    public function test_only_admin_can_manage_student_card_template(): void
    {
        $this->actingAs($this->makeUser('kesiswaan'))->get('/template_kartu')->assertForbidden();
    }

    public function test_admin_can_generate_a_printable_student_qr_based_on_induk(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'X AKL 1']);
        $student = Student::query()->create([
            'induk' => '1234567890', 'nama' => 'Siswa QR Uji', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)
            ->get('/data_siswa')
            ->assertOk()->assertSee('data-student-list', false)
            ->assertSee('data-student-qr-link', false)->assertSee('<dialog class="student-qr-dialog"', false);

        $this->actingAs($admin)
            ->get('/data_siswa/'.$student->id.'/qr')
            ->assertOk()->assertSee('Siswa QR Uji')->assertSee('1234567890')->assertSee('AKL/AK')->assertSee('Cetak Kartu')
            ->assertDontSee('Nomor induk:')->assertDontSee('Rombel:')->assertDontSee('X AKL 1')
            ->assertSee('QR terpisah')->assertSee('class="qr-standalone-image"', false);

        $cardHtml = $this->get('/data_siswa/'.$student->id.'/qr')->getContent();
        $this->assertLessThan(strpos($cardHtml, 'class="qr-student-code"'), strpos($cardHtml, 'class="qr-student-details"'));
        $this->assertStringContainsString('class="qr-standalone-image"', $cardHtml);

        $response = $this->get('/data_siswa/'.$student->id.'/qr.svg')
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $expectedSvg = (new SvgWriter)->write(new QrCode(
            data: $student->induk,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 16,
        ))->getString();

        $this->assertSame($expectedSvg, $response->getContent());
        $this->get('/data_siswa/'.$student->id.'/qr-haid.svg')->assertNotFound();

        $femaleStudent = Student::query()->create([
            'induk' => '1234567891', 'nama' => 'Siswi QR Haid', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        $haidSvg = $this->get('/data_siswa/'.$femaleStudent->id.'/qr-haid.svg')
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $expectedHaidSvg = (new SvgWriter)->write(new QrCode(
            data: $femaleStudent->induk,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 16,
        ))->getString();
        $this->assertSame($expectedHaidSvg, $haidSvg->getContent());
    }

    public function test_student_photo_upload_is_stored_by_induk_and_shown_on_qr_card(): void
    {
        Storage::fake('local');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '7654321', 'nama' => 'Foto Uji', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->post('/data_siswa/foto', ['induk' => $student->induk, 'foto' => UploadedFile::fake()->image('potret.jpg')])
            ->assertRedirect(route('students.qr', $student));

        $this->assertTrue(Storage::disk('local')->exists('foto-siswa/765/7654321.jpg'));
        $this->get('/data_siswa/'.$student->id.'/qr')
            ->assertOk()->assertSee(route('students.photo', $student), false);
        $this->get('/data_siswa/'.$student->id.'/foto')
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertFalse(Schema::hasColumn('siswa', 'foto_path'));
    }

    public function test_student_card_pdf_photo_is_cropped_to_cover_without_stretching(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to verify student card photo cropping.');
        }

        $sourceImage = imagecreatetruecolor(400, 200);
        $red = imagecolorallocate($sourceImage, 240, 20, 20);
        $blue = imagecolorallocate($sourceImage, 20, 20, 240);
        imagefill($sourceImage, 0, 0, $red);
        imagefilledrectangle($sourceImage, 0, 0, 59, 199, $blue);
        imagefilledrectangle($sourceImage, 340, 0, 399, 199, $blue);

        ob_start();
        imagepng($sourceImage);
        $sourceContents = (string) ob_get_clean();
        imagedestroy($sourceImage);

        $photoDataUri = app(StudentCardPhotoProcessor::class)->coverDataUri($sourceContents, 'image/png');
        $processedContents = base64_decode(substr($photoDataUri, strpos($photoDataUri, ',') + 1), true);
        $this->assertNotFalse($processedContents);

        $dimensions = getimagesizefromstring($processedContents);
        $this->assertSame(500, $dimensions[0]);
        $this->assertSame(637, $dimensions[1]);

        $processedImage = imagecreatefromstring($processedContents);
        $edgePixel = imagecolorat($processedImage, 0, 318);
        imagedestroy($processedImage);
        $this->assertGreaterThan(200, ($edgePixel >> 16) & 0xff);
        $this->assertLessThan(80, $edgePixel & 0xff);

        $templatePhotoDataUri = app(StudentCardPhotoProcessor::class)->coverDataUri($sourceContents, 'image/png', 500, 597);
        $templatePhotoContents = base64_decode(substr($templatePhotoDataUri, strpos($templatePhotoDataUri, ',') + 1), true);
        $this->assertNotFalse($templatePhotoContents);
        $templateDimensions = getimagesizefromstring($templatePhotoContents);
        $this->assertSame(500, $templateDimensions[0]);
        $this->assertSame(597, $templateDimensions[1]);
    }

    public function test_uploaded_student_photos_are_resized_and_compressed(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to verify student photo optimization.');
        }

        $sourceImage = imagecreatetruecolor(2400, 3200);
        $background = imagecolorallocate($sourceImage, 80, 120, 160);
        $detail = imagecolorallocate($sourceImage, 210, 90, 60);
        imagefill($sourceImage, 0, 0, $background);
        imagefilledrectangle($sourceImage, 300, 400, 2100, 2800, $detail);
        ob_start();
        imagejpeg($sourceImage, null, 100);
        $sourceContents = (string) ob_get_clean();
        imagedestroy($sourceImage);

        $optimized = app(StudentCardPhotoProcessor::class)->optimizeForStorage($sourceContents, 'jpg');
        $dimensions = getimagesizefromstring($optimized['contents']);

        $this->assertSame('jpg', $optimized['extension']);
        $this->assertLessThanOrEqual(1200, $dimensions[0]);
        $this->assertLessThanOrEqual(1600, $dimensions[1]);
        $this->assertLessThan(100 * 1024, strlen($optimized['contents']));
        $this->assertLessThan(strlen($sourceContents), strlen($optimized['contents']));
    }

    public function test_oversized_unprocessable_photo_is_not_accepted_for_storage(): void
    {
        $optimized = app(StudentCardPhotoProcessor::class)->optimizeForStorage(str_repeat('x', 100 * 1024), 'jpg');

        $this->assertNull($optimized);
    }

    public function test_existing_student_photos_can_be_compressed_by_artisan_command(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to verify student photo optimization.');
        }

        Storage::fake('local');
        $sourceImage = imagecreatetruecolor(1200, 1600);
        $background = imagecolorallocate($sourceImage, 80, 120, 160);
        imagefill($sourceImage, 0, 0, $background);
        ob_start();
        imagepng($sourceImage, null, 0);
        $sourceContents = (string) ob_get_clean();
        imagedestroy($sourceImage);
        Storage::disk('local')->put('foto-siswa/126/1260001.png', $sourceContents);

        $sourceImage = imagecreatetruecolor(2400, 3200);
        $background = imagecolorallocate($sourceImage, 80, 120, 160);
        imagefill($sourceImage, 0, 0, $background);
        ob_start();
        imagejpeg($sourceImage, null, 100);
        $largeJpeg = (string) ob_get_clean();
        imagedestroy($sourceImage);
        Storage::disk('local')->put('foto-siswa/126/1260002.JPG', $largeJpeg);

        Artisan::call('students:photos-optimize');

        $this->assertFalse(Storage::disk('local')->exists('foto-siswa/126/1260001.png'));
        $this->assertTrue(Storage::disk('local')->exists('foto-siswa/126/1260001.jpg'));
        $this->assertLessThan(100 * 1024, Storage::disk('local')->size('foto-siswa/126/1260001.jpg'));
        $this->assertTrue(Storage::disk('local')->exists('foto-siswa/126/1260002.JPG'));
        $this->assertLessThan(100 * 1024, Storage::disk('local')->size('foto-siswa/126/1260002.JPG'));
    }

    public function test_student_edit_form_can_preview_replace_delete_and_rekey_photo(): void
    {
        Storage::fake('local');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '7654321', 'nama' => 'Foto Ubah Uji', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        Storage::disk('local')->put('foto-siswa/765/7654321.jpg', UploadedFile::fake()->image('lama.jpg')->getContent());
        $this->actingAs($this->makeUser('admin'));

        $this->get('/data_siswa?edit='.$student->id)->assertOk()->assertSee(route('students.photo', $student), false);
        $this->post('/data_siswa', [
            'id' => $student->id, 'induk' => '7654322', 'nama' => 'Foto Ubah Uji', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ])->assertRedirect('/data_siswa');
        $this->assertFalse(Storage::disk('local')->exists('foto-siswa/765/7654321.jpg'));
        $this->assertTrue(Storage::disk('local')->exists('foto-siswa/765/7654322.jpg'));

        $this->post('/data_siswa', [
            'id' => $student->id, 'induk' => '7654322', 'nama' => 'Foto Ubah Uji', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
            'foto' => UploadedFile::fake()->image('baru.png'),
        ])->assertRedirect('/data_siswa');
        $this->assertFalse(Storage::disk('local')->exists('foto-siswa/765/7654322.jpg'));
        $this->assertTrue(Storage::disk('local')->exists('foto-siswa/765/7654322.jpg') || Storage::disk('local')->exists('foto-siswa/765/7654322.png'));

        $this->post('/data_siswa', [
            'id' => $student->id, 'induk' => '7654322', 'nama' => 'Foto Ubah Uji', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
            'hapus_foto' => '1',
        ])->assertRedirect('/data_siswa');
        $this->assertFalse(Storage::disk('local')->exists('foto-siswa/765/7654322.jpg'));
        $this->assertFalse(Storage::disk('local')->exists('foto-siswa/765/7654322.png'));
        $this->get('/data_siswa/'.$student->id.'/qr')->assertSee('Foto belum tersedia');
    }

    public function test_classroom_major_labels_use_the_requested_aliases(): void
    {
        $cases = [
            'X PPLG 1' => 'PPLG/RPL',
            'XI TKJ 2' => 'TJKT/TKJ',
            'XI TSM 1' => 'TSM/TO',
            'XII TM 1' => 'TPM/TM',
            'X AK 1' => 'AKL/AK',
            'XII PS 1' => 'PS/BR/BD',
            'XI PS 1' => 'PS/BR/BD',
            'XI PS BR 1' => 'PS/BR/BD',
            'XII PS BD 1' => 'PS/BR/BD',
            'XI BR 1' => 'PS/BR/BD',
            'XII BD 1' => 'PS/BR/BD',
            'XI MPLB 1' => 'MPLB/MP',
        ];

        foreach ($cases as $classroomName => $expectedLabel) {
            $classroom = new Classroom(['nama' => $classroomName]);
            $this->assertSame($expectedLabel, $classroom->majorLabel());
        }
    }

    public function test_student_qr_card_download_returns_a_pdf(): void
    {
        Storage::fake('local');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $student = Student::query()->create([
            'induk' => '2345678', 'nama' => 'PDF QR Uji', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        Storage::disk('local')->put('foto-siswa/234/2345678.jpg', UploadedFile::fake()->image('foto.jpg')->getContent());

        $response = $this->actingAs($this->makeUser('admin'))
            ->get('/data_siswa/'.$student->id.'/qr.pdf')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="QR-2345678.pdf"');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        preg_match('/\/MediaBox\s*\[0\.000 0\.000 ([\d.]+) ([\d.]+)\]/', $response->getContent(), $pageSize);
        $this->assertEqualsWithDelta(53.98 * 72 / 25.4, (float) $pageSize[1], 0.1);
        $this->assertEqualsWithDelta(85.60 * 72 / 25.4, (float) $pageSize[2], 0.1);
        preg_match_all('/\/Type\s*\/Page\b/', $response->getContent(), $pages);
        $this->assertCount(1, $pages[0]);
    }

    public function test_selected_students_are_tiled_nine_per_a4_page(): void
    {
        Storage::fake('local');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        $students = collect(range(1, 10))->map(fn (int $number) => Student::query()->create([
            'induk' => '12345'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'nama' => 'Siswa '.$number,
            'jenis_kelamin' => 'L',
            'id_kelas' => $classroom->id,
        ]));

        $response = $this->actingAs($this->makeUser('admin'))
            ->post('/data_siswa/qr/cetak', ['student_ids' => $students->pluck('id')->all()])
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Kartu-Siswa-Terpilih.pdf"');

        preg_match_all('/\/Type\s*\/Page\b/', $response->getContent(), $pages);
        $this->assertCount(2, $pages[0]);
        preg_match_all('/\/MediaBox\s*\[0\.000 0\.000 ([\d.]+) ([\d.]+)\]/', $response->getContent(), $pageSizes, PREG_SET_ORDER);
        $this->assertNotEmpty($pageSizes);
        foreach ($pageSizes as $pageSize) {
            $this->assertEqualsWithDelta(210 * 72 / 25.4, (float) $pageSize[1], 0.1);
            $this->assertEqualsWithDelta(297 * 72 / 25.4, (float) $pageSize[2], 0.1);
        }
    }

    public function test_mass_qr_download_filters_by_class_grade_and_major(): void
    {
        Storage::fake('local');
        $classroom = Classroom::query()->create(['nama' => 'X PPLG 1']);
        $otherGradeClass = Classroom::query()->create(['nama' => 'X TJKT 1']);
        $otherMajorClass = Classroom::query()->create(['nama' => 'XI PPLG 1']);
        $excludedClass = Classroom::query()->create(['nama' => 'XII TJKT 1']);
        $createStudent = static function (int $number, Classroom $studentClass): Student {
            return Student::query()->create([
                'induk' => (string) (7200000 + $number),
                'nama' => 'Unduh '.$number,
                'jenis_kelamin' => 'L',
                'id_kelas' => $studentClass->id,
            ]);
        };

        foreach (range(1, 9) as $number) {
            $createStudent($number, $classroom);
        }
        $createStudent(10, $otherGradeClass);
        $createStudent(11, $otherMajorClass);
        $createStudent(12, $excludedClass);

        $this->actingAs($this->makeUser('admin'));
        $classPdf = $this->post('/data_siswa/qr/cetak-massal', [
            'scope' => 'class', 'classroom_id' => $classroom->id,
        ])->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="Kartu-Siswa-X-PPLG-1.pdf"');
        preg_match_all('/\/Type\s*\/Page\b/', $classPdf->getContent(), $classPages);
        $this->assertCount(1, $classPages[0]);

        $gradePdf = $this->post('/data_siswa/qr/cetak-massal', [
            'scope' => 'grade', 'grade' => 'X',
        ])->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="Kartu-Siswa-Kelas-X.pdf"');
        preg_match_all('/\/Type\s*\/Page\b/', $gradePdf->getContent(), $gradePages);
        $this->assertCount(2, $gradePages[0]);

        $majorPdf = $this->post('/data_siswa/qr/cetak-massal', [
            'scope' => 'major', 'major' => 'PPLG/RPL',
        ])->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="Kartu-Siswa-PPLG-RPL.pdf"');
        preg_match_all('/\/Type\s*\/Page\b/', $majorPdf->getContent(), $majorPages);
        $this->assertCount(2, $majorPages[0]);
    }

    public function test_manual_qr_selection_has_its_own_page_and_batch_limit_is_enforced(): void
    {
        Storage::fake('local');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        Student::query()->create([
            'induk' => '7300001', 'nama' => 'Pilihan Manual', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        $this->actingAs($this->makeUser('admin'));

        $this->get('/data_siswa')->assertOk()->assertDontSee('student-qr-select');
        $this->get('/data_siswa/kartu/manual')->assertOk()->assertSee('student-qr-select');
        $this->post('/data_siswa/qr/cetak-massal', ['scope' => 'major', 'major' => 'Tidak Ada'])
            ->assertSessionHasErrors('scope');

        foreach (range(1, 100) as $number) {
            Student::query()->create([
                'induk' => (string) (7400000 + $number),
                'nama' => 'Batas '.$number,
                'jenis_kelamin' => 'L',
                'id_kelas' => $classroom->id,
            ]);
        }
        $this->post('/data_siswa/qr/cetak-massal', ['scope' => 'class', 'classroom_id' => $classroom->id])
            ->assertSessionHasErrors('scope');
    }

    public function test_student_can_login_with_induk_and_only_access_their_card(): void
    {
        Storage::fake('local');
        $classroom = Classroom::query()->create(['nama' => 'XII PS 1']);
        $firstStudent = Student::query()->create([
            'induk' => '1234501', 'nama' => 'Siswa Portal Pertama', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        Student::query()->create([
            'induk' => '1234502', 'nama' => 'Siswa Portal Kedua', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        Storage::disk('local')->put('foto-siswa/123/1234501.jpg', UploadedFile::fake()->image('foto.jpg')->getContent());
        Storage::disk('local')->put('template-kartu/active.png', UploadedFile::fake()->image('card.png', 600, 1000)->getContent());

        $this->post('/siswa/login', ['induk' => $firstStudent->induk, 'pin' => 'wrong-pin'])
            ->assertSessionHasErrors('induk');
        $this->post('/siswa/login', ['induk' => $firstStudent->induk, 'pin' => 'siswa123'])
            ->assertRedirect(route('student.portal.card'));

        $this->assertGuest();
        $this->get('/siswa/kartu')->assertOk()
            ->assertSee('Siswa Portal Pertama')->assertSee('1234501')->assertSee('PS/BD')
            ->assertDontSee('XII PS 1')->assertDontSee('Nomor induk:')
            ->assertDontSee('Siswa Portal Kedua')->assertSee('data:image/jpeg;base64,', false)
            ->assertSee('height: 85.6mm;', false)
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee(route('student.portal.pdf'), false)->assertSee(route('student.portal.pin.edit'), false)
            ->assertSee('Keluar')->assertSee('QR terpisah');
        $cardHtml = $this->get('/siswa/kartu')->getContent();
        $this->assertLessThan(strpos($cardHtml, 'class="qr-student-code"'), strpos($cardHtml, 'class="qr-student-details"'));
        $pdf = $this->get('/siswa/kartu/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Kartu-1234501.pdf"');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        preg_match('/\/MediaBox\s*\[0\.000 0\.000 ([\d.]+) ([\d.]+)\]/', $pdf->getContent(), $pageSize);
        $this->assertEqualsWithDelta(53.98 * 72 / 25.4, (float) $pageSize[1], 0.1);
        $this->assertEqualsWithDelta(85.6 * 72 / 25.4, (float) $pageSize[2], 0.1);
        $this->get('/siswa/pin')->assertOk()->assertSee('Ubah PIN');
        $this->post('/siswa/pin', [
            'current_pin' => 'siswa123',
            'pin' => 'siswa-1234',
            'pin_confirmation' => 'siswa-1234',
        ])->assertRedirect(route('student.portal.pin.edit'));
        $this->get('/data_siswa')->assertRedirect('/staff/login');
        $this->post('/siswa/logout')->assertRedirect('/');
        $this->get('/siswa/kartu')->assertRedirect('/');
        $this->post('/siswa/login', ['induk' => $firstStudent->induk, 'pin' => 'siswa123'])
            ->assertSessionHasErrors('induk');
        $this->post('/siswa/login', ['induk' => $firstStudent->induk, 'pin' => 'siswa-1234'])
            ->assertRedirect(route('student.portal.card'));
        $this->get('/siswa/kartu')->assertOk()->assertSee('Siswa Portal Pertama');
    }

    public function test_staff_can_reset_a_student_portal_pin(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'XII PS 1']);
        $student = Student::query()->create([
            'induk' => '1234599', 'nama' => 'Siswa Reset PIN', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id,
        ]);
        $student->portal_pin = 'changed-pin-1';
        $student->save();

        $this->actingAs($this->makeUser('kesiswaan'))
            ->post(route('students.pin.reset', $student))
            ->assertRedirect(route('students.index'));

        $this->assertTrue(Hash::check('siswa123', (string) $student->fresh()->portal_pin));
        $this->assertFalse(Hash::check('changed-pin-1', (string) $student->fresh()->portal_pin));
    }

    public function test_staff_and_student_login_pages_are_marked_noindex(): void
    {
        $meta = '<meta name="robots" content="noindex, nofollow">';

        $this->get('/login')->assertOk()->assertSee($meta, false);
        $this->get('/siswa/login')->assertOk()->assertSee($meta, false);
    }

    public function test_admin_can_import_student_workbooks_transactionally(): void
    {
        $user = $this->makeUser('admin');
        $classroom = Classroom::query()->create(['nama' => 'XII IPA 2']);
        $existingStudent = Student::query()->create([
            'induk' => '7777', 'nama' => 'Calon Alumni', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ]);
        Attendance::query()->create(['id_siswa' => $existingStudent->id, 'tanggal' => '2026-09-28', 'status' => 'H']);
        $oldClassroom = Classroom::query()->create(['nama' => 'XI IPA 2']);
        $promotedStudent = Student::query()->create([
            'induk' => '6666', 'nama' => 'Siswa Naik Kelas', 'jenis_kelamin' => 'P', 'id_kelas' => $oldClassroom->id,
        ]);
        $path = tempnam(sys_get_temp_dir(), 'student-import-');
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('X IPA 2');
        $workbook->getActiveSheet()->fromArray([
            ['Nomor Induk', 'Nama', 'Jenis Kelamin'],
            ['8888', 'Impor Uji', 'P'],
            ['8889', 'Impor Uji', 'P'],
            ['6666', 'Siswa Naik Kelas', 'P'],
        ]);
        (new Xlsx($workbook))->save($path);
        $content = file_get_contents($path);
        unlink($path);

        $this->actingAs($user)->post('/import_siswa', [
            'excel' => UploadedFile::fake()->createWithContent('siswa.xlsx', $content),
        ])->assertRedirect('/import_siswa')->assertSessionHas('status');

        $this->assertDatabaseHas('siswa', ['induk' => '8888', 'nama' => 'Impor Uji', 'jenis_kelamin' => 'P']);
        $this->assertDatabaseMissing('siswa', ['induk' => '8889']);
        $this->assertSame(1, Student::query()->where('nama', 'Impor Uji')->count());
        $this->assertDatabaseHas('kelas', ['nama' => 'X IPA 2']);
        $this->assertDatabaseHas('siswa', ['induk' => '7777', 'is_alumni' => true]);
        $this->assertDatabaseHas('absensi', ['id_siswa' => $existingStudent->id, 'tanggal' => '2026-09-28', 'status' => 'H']);
        $newClassroom = Classroom::query()->where('nama', 'X IPA 2')->firstOrFail();
        $this->assertDatabaseHas('siswa', [
            'id' => $promotedStudent->id, 'induk' => '6666', 'id_kelas' => $newClassroom->id, 'is_alumni' => false,
        ]);
    }

    public function test_alumni_are_not_eligible_for_attendance_scan(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'XII IPA 2']);
        $student = Student::query()->create([
            'induk' => '7777', 'nama' => 'Siswa Alumni', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id, 'is_alumni' => true,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->postJson('/scan-qr', ['induk' => $student->induk])
            ->assertNotFound();

        $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id]);
    }

    public function test_student_management_list_loads_only_fifty_active_students_per_request(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        foreach (range(1, 51) as $number) {
            Student::query()->create([
                'induk' => (string) (12600000 + $number),
                'nama' => 'Siswa '.$number,
                'jenis_kelamin' => 'L',
                'id_kelas' => $classroom->id,
            ]);
        }
        Student::query()->create([
            'induk' => '12600999', 'nama' => 'Siswa Alumni', 'jenis_kelamin' => 'P', 'id_kelas' => $classroom->id, 'is_alumni' => true,
        ]);

        $this->actingAs($this->makeUser('admin'));
        $page = $this->get('/data_siswa')->assertOk()->assertSee('data-student-load-trigger', false);
        $this->assertStringNotContainsString('student-qr-select', $page->getContent());
        $this->assertStringContainsString('Riwayat', $page->getContent());
        $this->get('/data_siswa?cari=Siswa')->assertOk()->assertSee('51 siswa cocok.');

        $nextPage = $this->getJson('/data_siswa?page=2');
        $nextPage->assertOk()->assertJsonPath('count', 1)->assertJsonPath('nextPageUrl', null);
        $this->assertStringNotContainsString('student-qr-select', $nextPage->json('html'));
        $this->assertStringContainsString('Riwayat', $nextPage->json('html'));
        $this->assertStringNotContainsString('Siswa Alumni', $nextPage->json('html'));
    }

    public function test_attendance_table_loads_students_in_scroll_batches(): void
    {
        $classroom = Classroom::query()->create(['nama' => 'X IPA 1']);
        foreach (range(1, 51) as $number) {
            Student::query()->create([
                'induk' => (string) (12700000 + $number),
                'nama' => 'Absensi '.$number,
                'jenis_kelamin' => 'L',
                'id_kelas' => $classroom->id,
            ]);
        }

        $this->actingAs($this->makeUser('absensi'));
        $page = $this->get('/staff?bulan=9&tahun=2026&kelas=X%20IPA%201&minggu_tabel=2026-09-28')
            ->assertOk()->assertSee('data-progressive-table', false);
        preg_match_all('/data-student="(\d+)"/', $page->getContent(), $matches);
        $this->assertCount(50, array_unique($matches[1]));

        $nextPage = $this->getJson('/staff?bulan=9&tahun=2026&kelas=X%20IPA%201&minggu_tabel=2026-09-28&page=2');
        $nextPage->assertOk()->assertJsonPath('count', 1)->assertJsonPath('nextPageUrl', null);
        $this->assertSame(1, substr_count($nextPage->json('html'), 'class="attendance-select"'));
    }

    public function test_excel_and_a4_exports_return_real_workbooks(): void
    {
        $user = $this->makeUser('admin');
        $classroom = Classroom::query()->create(['nama' => 'X IPA 3']);
        $student = Student::query()->create([
            'induk' => '9999', 'nama' => 'Ekspor Uji', 'jenis_kelamin' => 'L', 'is_irma' => true, 'id_kelas' => $classroom->id,
        ]);
        Attendance::query()->create(['id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'H']);

        $regularExport = $this->actingAs($user)->get('/export_excel?tipe=minggu&minggu=2026-09-28')
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $regularPath = tempnam(sys_get_temp_dir(), 'regular-export-');
        file_put_contents($regularPath, $regularExport->getContent());
        try {
            $regularWorkbook = IOFactory::load($regularPath);
            $regularSheet = $regularWorkbook->getSheetByName('Angkatan X');
            $this->assertStringContainsString(' - 28/09/2026 - 02/10/2026', $regularSheet->getCell('A1')->getValue());
            $this->assertSame('X IPA 3', $regularSheet->getCell('A2')->getValue());
            $this->assertSame('H', $regularSheet->getCell('D4')->getValue());
            $this->assertSame('-', $regularSheet->getCell('E4')->getValue());
            $this->assertSame('D9F2DF', $regularSheet->getStyle('B4')->getFill()->getStartColor()->getRGB());
        } finally {
            if (is_file($regularPath)) {
                unlink($regularPath);
            }
        }
        $this->get('/export_a4?dari=2026-09-28&format=xlsx')
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get('/export_a4?dari=2026-09-28&format=docx')
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_a4_xlsx_uses_native_grade_sheets_and_one_print_page_per_class(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $firstClass = Classroom::query()->create(['nama' => 'X IPA 3']);
        $secondClass = Classroom::query()->create(['nama' => 'X IPA 4']);
        Student::query()->create([
            'induk' => '8101', 'nama' => 'Siswa IRMA', 'jenis_kelamin' => 'L', 'is_irma' => true, 'id_kelas' => $firstClass->id,
        ]);
        Student::query()->create([
            'induk' => '8102', 'nama' => 'Siswa Kedua', 'jenis_kelamin' => 'P', 'is_nonis' => true, 'id_kelas' => $secondClass->id,
        ]);

        $response = $this->get('/export_a4?dari=2026-09-28&format=xlsx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'a4-parity-');
        file_put_contents($path, $response->getContent());

        try {
            $workbook = IOFactory::load($path);
            $this->assertSame(['Angkatan X', 'Angkatan XI', 'Angkatan XII'], $workbook->getSheetNames());

            $sheet = $workbook->getSheetByName('Angkatan X');
            $this->assertSame('ROMBEL: X IPA 3', $sheet->getCell('A1')->getValue());
            $this->assertSame('ROMBEL: X IPA 4', $sheet->getCell('A7')->getValue());
            $this->assertArrayHasKey('A6', $sheet->getBreaks());
            $this->assertSame('SEN'."\n".'28/09', $sheet->getCell('D5')->getValue());
            $this->assertSame('Siswa IRMA', $sheet->getCell('B6')->getValue());
            $this->assertNull($sheet->getCell('D6')->getValue());
            $this->assertSame('D9F2DF', $sheet->getStyle('B6')->getFill()->getStartColor()->getRGB());
            $this->assertSame('E5EFFF', $sheet->getStyle('B9')->getFill()->getStartColor()->getRGB());
            $this->assertSame(PageSetup::PAPERSIZE_A4, $sheet->getPageSetup()->getPaperSize());
            $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $sheet->getPageSetup()->getOrientation());
            $this->assertTrue($sheet->getPageSetup()->getFitToPage());
            $this->assertSame(1, $sheet->getPageSetup()->getFitToWidth());
            $this->assertSame(0, $sheet->getPageSetup()->getFitToHeight());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_a4_docx_has_native_week_headers_class_pages_and_irma_highlight(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $firstClass = Classroom::query()->create(['nama' => 'X IPA 3']);
        $secondClass = Classroom::query()->create(['nama' => 'X IPA 4']);
        Student::query()->create([
            'induk' => '8201', 'nama' => 'Siswa IRMA DOCX', 'jenis_kelamin' => 'L', 'is_irma' => true, 'id_kelas' => $firstClass->id,
        ]);
        Student::query()->create([
            'induk' => '8202', 'nama' => 'Siswa Kedua DOCX', 'jenis_kelamin' => 'P', 'is_nonis' => true, 'id_kelas' => $secondClass->id,
        ]);

        $response = $this->get('/export_a4?dari=2026-09-28&format=docx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'a4-docx-parity-');
        file_put_contents($path, $response->getContent());
        $archive = new \ZipArchive;

        try {
            $this->assertSame(true, $archive->open($path));
            $documentXml = $archive->getFromName('word/document.xml');
            $this->assertIsString($documentXml);
            $this->assertStringContainsString('MINGGU 1', $documentXml);
            $this->assertStringContainsString('MINGGU 2', $documentXml);
            $this->assertStringContainsString('ROMBEL: X IPA 3', $documentXml);
            $this->assertStringContainsString('ROMBEL: X IPA 4', $documentXml);
            $this->assertStringContainsString('D9F2DF', $documentXml);
            $this->assertStringContainsString('E5EFFF', $documentXml);
            $this->assertStringContainsString('nextPage', $documentXml);
        } finally {
            $archive->close();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_admin_can_assign_nonis_student_category(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $classroom = Classroom::query()->create(['nama' => 'X IPA Nonis']);

        $this->post('/data_siswa', [
            'induk' => '8301',
            'nama' => 'Siswa Nonis',
            'jenis_kelamin' => 'P',
            'id_kelas' => $classroom->id,
            'kategori' => 'nonis',
        ])->assertRedirect('/data_siswa');

        $student = Student::query()->where('induk', '8301')->firstOrFail();
        $this->assertFalse($student->is_irma);
        $this->assertTrue($student->is_nonis);
        $this->get('/data_siswa')->assertOk()->assertSee('Siswa Nonis')->assertSee('Nonis');
        $this->get('/staff?kelas='.urlencode($classroom->nama))->assertOk()->assertSee('nonis-row', false);

        $this->post('/data_siswa', [
            'id' => $student->id,
            'induk' => $student->induk,
            'nama' => $student->nama,
            'jenis_kelamin' => 'P',
            'id_kelas' => $classroom->id,
            'kategori' => 'irma',
        ])->assertRedirect('/data_siswa');

        $student->refresh();
        $this->assertTrue($student->is_irma);
        $this->assertFalse($student->is_nonis);
        $this->get('/data_siswa')->assertOk()->assertSee('IRMA');

        $this->post('/data_siswa', [
            'induk' => '8302',
            'nama' => 'Kategori Tidak Valid',
            'jenis_kelamin' => 'L',
            'id_kelas' => $classroom->id,
            'kategori' => 'lainnya',
        ])->assertSessionHasErrors('kategori');
    }

    public function test_admin_can_manage_classes_students_and_users(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $this->post('/kelas', ['nama' => 'XI IPA 1'])->assertRedirect('/data_siswa');
        $classroom = Classroom::query()->where('nama', 'XI IPA 1')->firstOrFail();
        $this->post('/data_siswa', [
            'induk' => '1111', 'nama' => 'Kelola Uji', 'jenis_kelamin' => 'L', 'id_kelas' => $classroom->id,
        ])->assertRedirect('/data_siswa');
        $student = Student::query()->where('induk', '1111')->firstOrFail();
        Attendance::query()->create(['id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'I']);

        $this->post('/data_user', [
            'username' => 'petugas', 'nama' => 'Petugas Baru', 'role' => 'absensi', 'password' => 'password123',
        ])->assertRedirect('/data_user');
        $this->assertDatabaseHas('users', ['username' => 'petugas', 'role' => 'absensi']);
        $this->delete('/data_user/'.$admin->id)->assertSessionHasErrors('user');
        $this->delete('/kelas/'.$classroom->id)->assertSessionHasErrors('kelas');

        $this->postJson('/delete_all', ['bulan' => 9, 'tahun' => 2026, 'kelas' => 'XI IPA 1'])
            ->assertOk()->assertJson(['success' => true, 'deleted' => 1]);
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id]);
        Attendance::query()->create(['id_siswa' => $student->id, 'tanggal' => '2026-09-28', 'status' => 'I']);

        $this->delete('/data_siswa/'.$student->id)->assertRedirect('/data_siswa');
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id]);
        $this->delete('/kelas/'.$classroom->id)->assertRedirect('/data_siswa');
    }

    public function test_authenticated_pages_render_for_admin(): void
    {
        $this->actingAs($this->makeUser('admin'));

        foreach (['/staff', '/laporan', '/alpha', '/data_siswa', '/data_siswa/kartu/manual', '/data_user', '/import_siswa', '/cetak_absensi'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_existing_laravel_users_are_converted_without_losing_accounts(): void
    {
        Schema::drop('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        DB::table('users')->insert([
            'name' => 'Pengguna Lama', 'email' => 'petugas@example.test', 'password' => Hash::make('password123'),
        ]);

        $migration = require database_path('migrations/2026_09_29_000001_convert_laravel_users_to_native_accounts.php');
        $migration->up();

        $this->assertDatabaseHas('users', [
            'username' => 'petugas@example.test', 'nama' => 'Pengguna Lama', 'role' => 'absensi',
        ]);
        $this->assertFalse(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasColumn('users', 'name'));
    }

    private function makeUser(string $role): User
    {
        return User::query()->create([
            'username' => $role,
            'nama' => ucfirst($role),
            'role' => $role,
            'password' => Hash::make('password123'),
        ]);
    }
}
