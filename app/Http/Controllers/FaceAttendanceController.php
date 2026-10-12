<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\AppSetting;
use AbsenShalat\Models\Student;
use AbsenShalat\Models\StudentFaceRegistration;
use App\Services\AttendanceRecorder;
use App\Services\StudentFaceRecognition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FaceAttendanceController extends Controller
{
    public function scanner(): View
    {
        return view('attendance.face-scan', [
            'attendanceEndpoint' => route('face.attendance.record'),
            'registrationEndpoint' => null,
            'student' => null,
            'isAdminRegistration' => false,
        ]);
    }

    public function studentScanner(Request $request): View
    {
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');
        abort_if($student->is_alumni, 404);

        return view('attendance.face-scan', [
            'attendanceEndpoint' => route('student.portal.face.attendance'),
            'registrationEndpoint' => route('student.portal.face.register'),
            'student' => $student,
            'isAdminRegistration' => false,
        ]);
    }

    public function adminRegistration(Student $student): View
    {
        abort_if($student->is_alumni, 404);

        return view('attendance.face-scan', [
            'attendanceEndpoint' => null,
            'registrationEndpoint' => route('students.face.register', $student),
            'student' => $student,
            'isAdminRegistration' => true,
        ]);
    }

    public function record(Request $request, StudentFaceRecognition $recognition, AttendanceRecorder $recorder): JsonResponse
    {
        $data = $request->validate(['jenis' => ['nullable', Rule::in(['haid'])]]);
        $descriptors = $this->validatedDescriptors($request);
        if (! $this->scanWindowOpen()) {
            return response()->json(['success' => false, 'message' => $this->closedMessage()], 403);
        }
        if (! StudentFaceRegistration::query()->whereHas('student', fn ($query) => $query->where('is_alumni', false))->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data wajah siswa yang terdaftar. Daftarkan wajah siswa terlebih dahulu.',
            ], 404);
        }

        $student = $recognition->identify($descriptors);
        if ($student === null) {
            return response()->json([
                'success' => false,
                'message' => 'Wajah tidak cocok atau sampel tidak konsisten dengan data siswa terdaftar.',
            ], 404);
        }

        $status = ($data['jenis'] ?? null) === 'haid' ? 'M' : 'H';
        if ($status === 'M' && $student->jenis_kelamin !== 'P') {
            return response()->json(['success' => false, 'message' => 'Keterangan haid hanya berlaku untuk siswi.'], 422);
        }

        return $this->attendanceResponse($student, $recorder, $status);
    }

    public function registerAdmin(Request $request, Student $student): JsonResponse
    {
        abort_if($student->is_alumni, 404);
        $registration = $this->storeRegistration($request, $student);
        if ($registration === null) {
            return $this->duplicateRegistrationResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi wajah '.$student->nama.' berhasil disimpan.',
            'kode_wajah' => $registration->face_code,
        ]);
    }

    public function registerStudent(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');
        abort_if($student->is_alumni, 404);
        $registration = $this->storeRegistration($request, $student);
        if ($registration === null) {
            return $this->duplicateRegistrationResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi wajah berhasil disimpan untuk akun ini.',
            'kode_wajah' => $registration->face_code,
        ]);
    }

    public function deleteAdminRegistration(Student $student): RedirectResponse
    {
        $student->faceRegistration()->delete();

        return redirect()->route('students.face', $student)->with('status', 'Data wajah berhasil dihapus.');
    }

    public function deleteStudentRegistration(Request $request): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');
        abort_if($student->is_alumni, 404);
        $student->faceRegistration()->delete();

        return redirect()->route('student.portal.face')->with('status', 'Data wajah berhasil dihapus.');
    }

    public function recordStudent(Request $request, StudentFaceRecognition $recognition, AttendanceRecorder $recorder): JsonResponse
    {
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');
        abort_if($student->is_alumni, 404);
        $descriptors = $this->validatedDescriptors($request);

        if (! $this->scanWindowOpen()) {
            return response()->json(['success' => false, 'message' => $this->closedMessage()], 403);
        }
        if (! $student->faceRegistration()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Wajah untuk akun ini belum terdaftar. Daftarkan wajah terlebih dahulu.',
            ], 404);
        }

        $matchedStudent = $recognition->identify($descriptors);
        if ($matchedStudent === null || $matchedStudent->id !== $student->id) {
            return response()->json(['success' => false, 'message' => 'Wajah tidak cocok dengan akun siswa yang sedang digunakan.'], 404);
        }

        return $this->attendanceResponse($student, $recorder, 'H');
    }

    private function storeRegistration(Request $request, Student $student): ?StudentFaceRegistration
    {
        $data = $request->validate([
            'induk' => ['required', 'string', 'regex:/^\d+$/', 'max:30'],
            'descriptors' => ['required', 'array', 'size:5'],
            'descriptors.*' => ['required', 'array', 'size:128'],
            'descriptors.*.*' => ['required', 'numeric'],
        ], ['induk.regex' => 'Nomor induk harus berupa angka.']);

        if ($data['induk'] !== $student->induk) {
            throw ValidationException::withMessages(['induk' => 'Nomor induk tidak sesuai dengan siswa yang dipilih.']);
        }

        $descriptors = array_map(
            fn (array $descriptor): array => $this->normalizeDescriptor($descriptor),
            $data['descriptors']
        );

        return DB::transaction(function () use ($student, $descriptors): ?StudentFaceRegistration {
            $duplicate = app(StudentFaceRecognition::class)->duplicateRegistration($student->id, $descriptors);
            if ($duplicate !== null) {
                return null;
            }

            return StudentFaceRegistration::query()->updateOrCreate(
                ['student_id' => $student->id],
                ['face_descriptor' => json_encode($descriptors, JSON_THROW_ON_ERROR)]
            );
        });
    }

    private function duplicateRegistrationResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Wajah ini sudah terdaftar pada data siswa lain (NIS berbeda). Registrasi dibatalkan. Jika ini wajah Anda, hubungi admin untuk pemeriksaan.',
        ], 409);
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function validatedDescriptors(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'descriptors' => ['required', 'array', 'min:1', 'max:3'],
            'descriptors.*' => ['required', 'array', 'size:128'],
            'descriptors.*.*' => ['required', 'numeric'],
        ]);
        $data = $validator->validate();

        return array_map(fn (array $descriptor): array => $this->normalizeDescriptor($descriptor), $data['descriptors']);
    }

    /**
     * @param array<int, int|float|string> $descriptor
     * @return array<int, float>
     */
    private function normalizeDescriptor(array $descriptor): array
    {
        $normalized = [];
        foreach ($descriptor as $number) {
            $value = (float) $number;
            if (! is_finite($value)) {
                throw ValidationException::withMessages(['descriptor' => 'Data wajah mengandung nilai yang tidak valid.']);
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    private function scanWindowOpen(): bool
    {
        $start = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_START)->value('value');
        $end = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_END)->value('value');
        $current = now()->format('H:i');

        return $start === null || $end === null || ($current >= $start && $current <= $end);
    }

    private function closedMessage(): string
    {
        $start = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_START)->value('value');
        $end = AppSetting::query()->where('key', AppSetting::ATTENDANCE_SCAN_END)->value('value');

        return 'Scan wajah hanya dibuka pukul '.$start.' sampai '.$end.'.';
    }

    private function attendanceResponse(Student $student, AttendanceRecorder $recorder, string $status): JsonResponse
    {
        $result = $recorder->record($student, $status);
        $attendance = $result['attendance'];
        $alreadyRecorded = $result['already_recorded'];
        $label = $status === 'M' ? 'keterangan haid' : 'kehadiran';

        return response()->json([
            'success' => true,
            'already_present' => $result['already_present'],
            'already_recorded' => $alreadyRecorded,
            'message' => $alreadyRecorded
                ? ($status === 'M' ? 'Keterangan haid sudah tercatat hari ini.' : 'Siswa sudah tercatat hadir hari ini.')
                : ucfirst($label).' berhasil dicatat.',
            'student' => [
                'nama' => $student->nama,
                'induk' => $student->induk,
                'kelas' => $student->classroom->nama,
            ],
            'tanggal' => $attendance->tanggal,
            'waktu_scan' => $attendance->waktu_scan?->format('H:i:s'),
            'keterangan' => $status,
        ]);
    }
}
