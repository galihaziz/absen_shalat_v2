<?php

namespace Tests\Feature;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use AbsenShalat\Models\StudentFaceRegistration;
use AbsenShalat\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FaceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_and_remove_any_students_face_from_student_management(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeStudent('10001');
        $this->actingAs($admin);

        $this->get(route('face.scan'))
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Scan Wajah')
            ->assertSee('face-detection-guide')
            ->assertSee('faceDetectorMetrics')
            ->assertSee('Kamera belum aktif')
            ->assertSee('diam sampai hasil scan muncul')
            ->assertDontSee('faceStudentInduk');
        $this->get('/data_siswa')
            ->assertOk()
            ->assertSee(route('students.face', $student), false)
            ->assertSee('Daftarkan wajah');

        $this->get(route('students.face', $student))
            ->assertOk()
            ->assertSee('Nomor induk 10001')
            ->assertSee('Wajah belum terdaftar')
            ->assertDontSee('faceStudentInduk')
            ->assertSee('Jangan buru-buru pindah pose')
            ->assertSee('face-detection-guide');

        $registrationResponse = $this->postJson(route('students.face.register', $student), [
            'induk' => $student->induk,
            'descriptors' => $this->registrationDescriptors(),
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertCount(5, json_decode($student->fresh()->faceRegistration->face_descriptor, true));
        $this->assertSame($student->fresh()->faceRegistration->face_code, $registrationResponse->json('kode_wajah'));
        $this->assertNotEmpty($student->fresh()->faceRegistration->face_code);
        $this->get(route('students.face', $student))
            ->assertOk()
            ->assertSee($student->fresh()->faceRegistration->face_code)
            ->assertSee('Wajah sudah terdaftar')
            ->assertSee('data-camera-auto-start="false"', false)
            ->assertSee('Kamera belum aktif');

        $this->delete(route('students.face.delete', $student))->assertRedirect(route('students.face', $student));
        $this->assertDatabaseMissing('student_face_registrations', ['student_id' => $student->id]);
    }

    public function test_student_can_register_only_the_face_for_their_own_portal_account(): void
    {
        $student = $this->makeStudent('10002');

        $this->get(route('student.portal.login'))
            ->assertOk()
            ->assertSee('Ingin mendaftarkan wajah?')
            ->assertSee('Masuk terlebih dahulu');

        $this->get(route('student.portal.face'))->assertRedirect('/');

        $this->withSession(['student_portal_id' => $student->id])
            ->get(route('student.portal.card'))
            ->assertOk()
            ->assertSee('Daftarkan wajah')
            ->assertSee(route('student.portal.face'), false);

        $this->withSession(['student_portal_id' => $student->id])
            ->get(route('student.portal.face'))
            ->assertOk()
            ->assertSee('10002')
            ->assertSee('Daftarkan wajah')
            ->assertSee('Wajah belum terdaftar')
            ->assertDontSee('>Presensi</button>', false)
            ->assertSee('data-camera-auto-start="true"', false)
            ->assertDontSee('faceStudentInduk')
            ->assertSee('face-detection-guide');

        $this->withSession(['student_portal_id' => $student->id])
            ->postJson(route('student.portal.face.register'), [
                'induk' => '99999',
                'descriptors' => $this->registrationDescriptors(),
            ])->assertUnprocessable();
        $this->assertDatabaseMissing('student_face_registrations', ['student_id' => $student->id]);

        $this->withSession(['student_portal_id' => $student->id])
            ->postJson(route('student.portal.face.register'), [
                'induk' => $student->induk,
                'descriptors' => $this->registrationDescriptors(),
            ])->assertOk();

        $this->assertDatabaseHas('student_face_registrations', ['student_id' => $student->id]);
        $this->withSession(['student_portal_id' => $student->id])
            ->get(route('student.portal.face'))
            ->assertOk()
            ->assertSee('data-camera-auto-start="false"', false)
            ->assertSee('Kamera belum aktif')
            ->assertSee('Wajah sudah terdaftar')
            ->assertSee('Mulai kamera')
            ->assertDontSee('>Presensi</button>', false);

        $this->withSession(['student_portal_id' => $student->id])
            ->delete(route('student.portal.face.delete'))
            ->assertRedirect(route('student.portal.face'));
        $this->assertDatabaseMissing('student_face_registrations', ['student_id' => $student->id]);
    }

    public function test_registration_requires_all_five_guided_pose_descriptors(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeStudent('10009');

        $this->actingAs($admin)->postJson(route('students.face.register', $student), [
            'induk' => $student->induk,
            'descriptors' => array_slice($this->registrationDescriptors(), 0, 4),
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('student_face_registrations', ['student_id' => $student->id]);
    }

    public function test_registration_rejects_a_face_already_registered_to_another_student(): void
    {
        $admin = $this->makeUser('admin');
        $registeredStudent = $this->makeStudent('10010');
        $newStudent = $this->makeStudent('10011');
        $registeredDescriptors = $this->registrationDescriptors();
        StudentFaceRegistration::query()->create([
            'student_id' => $registeredStudent->id,
            'face_descriptor' => json_encode($registeredDescriptors, JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)->postJson(route('students.face.register', $newStudent), [
            'induk' => $newStudent->induk,
            'descriptors' => $registeredDescriptors,
        ])->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Wajah ini sudah terdaftar pada siswa '.$registeredStudent->nama.' (NIS '.$registeredStudent->induk.'). Registrasi dibatalkan.');

        $this->assertDatabaseMissing('student_face_registrations', ['student_id' => $newStudent->id]);
        $this->assertSame(1, StudentFaceRegistration::query()->count());
    }

    public function test_student_duplicate_registration_does_not_reveal_another_students_identity(): void
    {
        $registeredStudent = $this->makeStudent('10017');
        $portalStudent = $this->makeStudent('10018');
        $descriptors = $this->registrationDescriptors();
        StudentFaceRegistration::query()->create([
            'student_id' => $registeredStudent->id,
            'face_descriptor' => json_encode($descriptors, JSON_THROW_ON_ERROR),
        ]);

        $response = $this->withSession(['student_portal_id' => $portalStudent->id])
            ->postJson(route('student.portal.face.register'), [
                'induk' => $portalStudent->induk,
                'descriptors' => $descriptors,
            ])->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Wajah ini sudah terdaftar pada data siswa lain. Registrasi dibatalkan. Jika ini wajah Anda, hubungi admin untuk pemeriksaan.');

        $response->assertDontSee($registeredStudent->nama);
        $response->assertDontSee($registeredStudent->induk);
        $this->assertDatabaseMissing('student_face_registrations', ['student_id' => $portalStudent->id]);
    }

    public function test_different_face_registrations_receive_different_unique_codes(): void
    {
        $firstStudent = $this->makeStudent('10012');
        $secondStudent = $this->makeStudent('10013');
        $first = StudentFaceRegistration::query()->create([
            'student_id' => $firstStudent->id,
            'face_descriptor' => json_encode($this->registrationDescriptors(), JSON_THROW_ON_ERROR),
        ]);
        $second = StudentFaceRegistration::query()->create([
            'student_id' => $secondStudent->id,
            'face_descriptor' => json_encode(array_map(
                fn (int $pose): array => array_fill(0, 128, 0.6 + $pose * 0.001),
                range(0, 4)
            ), JSON_THROW_ON_ERROR),
        ]);

        $this->assertNotSame($first->face_code, $second->face_code);
        $this->assertSame(36, strlen($first->face_code));
        $this->assertSame(36, strlen($second->face_code));
    }

    public function test_face_attendance_records_the_existing_h_status_for_the_recognized_student(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeStudent('10003');
        StudentFaceRegistration::query()->create([
            'student_id' => $student->id,
            'face_descriptor' => json_encode($this->registrationDescriptors(), JSON_THROW_ON_ERROR),
        ]);
        $this->actingAs($admin)->postJson(route('face.attendance.record'), [
            'descriptors' => [
                array_fill(0, 128, 0.014),
                array_fill(0, 128, 0.014),
                array_fill(0, 128, 0.014),
            ],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('student.induk', '10003')
            ->assertJsonPath('keterangan', 'H');

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $student->id,
            'tanggal' => now()->toDateString(),
            'status' => 'H',
        ]);
    }

    public function test_face_attendance_accepts_one_fast_descriptor_after_stability_check(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeStudent('10014');
        StudentFaceRegistration::query()->create([
            'student_id' => $student->id,
            'face_descriptor' => json_encode($this->registrationDescriptors(), JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)->postJson(route('face.attendance.record'), [
            'descriptors' => [array_fill(0, 128, 0.014)],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('student.induk', '10014');

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $student->id,
            'tanggal' => now()->toDateString(),
            'status' => 'H',
        ]);
    }

    public function test_face_attendance_rejects_samples_that_identify_different_students(): void
    {
        $admin = $this->makeUser('admin');
        $firstStudent = $this->makeStudent('10015');
        $secondStudent = $this->makeStudent('10016');
        StudentFaceRegistration::query()->create([
            'student_id' => $firstStudent->id,
            'face_descriptor' => json_encode(array_fill(0, 128, 0.01), JSON_THROW_ON_ERROR),
        ]);
        StudentFaceRegistration::query()->create([
            'student_id' => $secondStudent->id,
            'face_descriptor' => json_encode(array_fill(0, 128, 0.6), JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)->postJson(route('face.attendance.record'), [
            'descriptors' => [
                array_fill(0, 128, 0.01),
                array_fill(0, 128, 0.6),
            ],
        ])->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('absensi', ['id_siswa' => $firstStudent->id]);
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $secondStudent->id]);
    }

    public function test_face_scanner_records_menstruation_status_only_for_female_students(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeStudent('10007');
        $student->jenis_kelamin = 'P';
        $student->save();
        StudentFaceRegistration::query()->create([
            'student_id' => $student->id,
            'face_descriptor' => json_encode($this->descriptor(), JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)->postJson(route('face.attendance.record'), [
            'jenis' => 'haid',
            'descriptors' => [$this->descriptor(), $this->descriptor(), $this->descriptor()],
        ])->assertOk()
            ->assertJsonPath('keterangan', 'M')
            ->assertJsonPath('message', 'Keterangan haid berhasil dicatat.');

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $student->id,
            'tanggal' => now()->toDateString(),
            'status' => 'M',
        ]);
    }

    public function test_face_scanner_rejects_menstruation_mode_for_male_students(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeStudent('10008');
        StudentFaceRegistration::query()->create([
            'student_id' => $student->id,
            'face_descriptor' => json_encode($this->descriptor(), JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($admin)->postJson(route('face.attendance.record'), [
            'jenis' => 'haid',
            'descriptors' => [$this->descriptor(), $this->descriptor(), $this->descriptor()],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Keterangan haid hanya berlaku untuk siswi.');

        $this->assertDatabaseMissing('absensi', ['id_siswa' => $student->id]);
    }

    public function test_face_scan_explains_when_no_students_have_registered_a_face(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->postJson(route('face.attendance.record'), [
            'descriptors' => [$this->descriptor(), $this->descriptor(), $this->descriptor()],
        ])->assertNotFound()
            ->assertJsonPath('message', 'Belum ada data wajah siswa yang terdaftar. Daftarkan wajah siswa terlebih dahulu.');
    }

    public function test_student_face_attendance_cannot_record_for_another_students_face(): void
    {
        $accountStudent = $this->makeStudent('10005');
        $otherStudent = $this->makeStudent('10006');
        StudentFaceRegistration::query()->create([
            'student_id' => $otherStudent->id,
            'face_descriptor' => json_encode($this->descriptor(), JSON_THROW_ON_ERROR),
        ]);

        $this->withSession(['student_portal_id' => $accountStudent->id])
            ->postJson(route('student.portal.face.attendance'), [
                'descriptors' => [$this->descriptor(), $this->descriptor(), $this->descriptor()],
            ])->assertNotFound();

        $this->assertDatabaseMissing('absensi', ['id_siswa' => $otherStudent->id]);
        $this->assertDatabaseMissing('absensi', ['id_siswa' => $accountStudent->id]);
    }

    public function test_only_admin_can_manage_faces_for_all_students(): void
    {
        $operator = $this->makeUser('absensi');
        $student = $this->makeStudent('10004');

        $this->actingAs($operator)->get(route('students.face', $student))->assertForbidden();
        $this->actingAs($operator)->postJson(route('students.face.register', $student), [
            'induk' => $student->induk,
            'descriptors' => $this->registrationDescriptors(),
        ])->assertForbidden();
    }

    private function makeUser(string $role): User
    {
        return User::query()->create([
            'username' => $role.'-user',
            'nama' => ucfirst($role),
            'role' => $role,
            'password' => Hash::make('password123'),
        ]);
    }

    private function makeStudent(string $induk): Student
    {
        $classroom = Classroom::query()->firstOrCreate(['nama' => 'X PPLG 1']);

        return Student::query()->create([
            'induk' => $induk,
            'nama' => 'Siswa '.$induk,
            'jenis_kelamin' => 'L',
            'id_kelas' => $classroom->id,
        ]);
    }

    /**
     * @return array<int, float>
     */
    private function descriptor(): array
    {
        return array_fill(0, 128, 0.01);
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function registrationDescriptors(): array
    {
        return array_map(
            fn (int $pose): array => array_fill(0, 128, 0.01 + $pose * 0.001),
            range(0, 4)
        );
    }
}
