<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use App\Services\StudentCardPhotoProcessor;
use App\Services\StudentCardTemplateStorage;
use App\Services\StudentQrCodeGenerator;
use App\Services\MajorCatalog;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    private const CARD_WIDTH_MM = 53.98;

    private const CARD_HEIGHT_MM = 85.6;

    private const CARD_FOLD_GAP_MM = 1;

    public function __construct(
        private readonly StudentCardPhotoProcessor $photoProcessor,
        private readonly StudentCardTemplateStorage $templateStorage
    )
    {
    }

    public function index(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('cari', ''));
        $showingAlumni = $request->query('status') === 'alumni';
        $studentCounts = Student::query()
            ->selectRaw('is_alumni, COUNT(*) as total')
            ->groupBy('is_alumni')
            ->pluck('total', 'is_alumni');
        $activeStudentCount = (int) $studentCounts->get(0, $studentCounts->get('0', 0));
        $alumniStudentCount = (int) $studentCounts->get(1, $studentCounts->get('1', 0));
        $studentQuery = Student::query()
            ->join('kelas as student_classrooms', 'student_classrooms.id', '=', 'siswa.id_kelas')
            ->select('siswa.*')
            ->where('siswa.is_alumni', $showingAlumni)
            ->with(['classroom', 'faceRegistration'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($studentQuery) use ($search): void {
                    $studentQuery->where('siswa.nama', 'like', '%'.$search.'%')
                        ->orWhere('siswa.induk', 'like', $search.'%')
                        ->orWhere('student_classrooms.nama', 'like', '%'.$search.'%');
                });
            });
        $matchingStudentCount = ! $request->expectsJson() && $search !== ''
            ? (clone $studentQuery)->count('siswa.id')
            : null;
        $students = $studentQuery
            ->orderBy('student_classrooms.nama')
            ->orderBy('siswa.nama')
            ->orderBy('siswa.id')
            ->simplePaginate(15)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('students._rows', compact('students', 'search', 'showingAlumni'))->render(),
                'nextPageUrl' => $students->nextPageUrl(),
                'count' => $students->count(),
            ]);
        }

        $classes = Classroom::query()
            ->withCount(['students' => fn ($query) => $query->where('is_alumni', false)])
            ->orderBy('nama')->get();
        $normalizedSearch = mb_strtolower(preg_replace('/\s+/u', ' ', trim($search)) ?? trim($search), 'UTF-8');
        $matchingPhotoClass = $normalizedSearch === ''
            ? null
            : $classes->first(fn (Classroom $class): bool => mb_strtolower(
                preg_replace('/\s+/u', ' ', trim($class->nama)) ?? trim($class->nama),
                'UTF-8'
            ) === $normalizedSearch);
        $photoDefaultClassroomId = $matchingPhotoClass?->id;
        $majorCatalog = app(MajorCatalog::class);
        $classroomNames = $classes->pluck('nama');
        $majorGroups = $majorCatalog->groups($classroomNames);
        $availableRelatedMajors = $majorCatalog->availableMajors($classroomNames);
        $grades = $classes->map(fn (Classroom $class): ?string => $this->classroomGrade($class->nama))
            ->filter()->unique()
            ->sortBy(fn (string $grade): int => ['X' => 10, 'XI' => 11, 'XII' => 12][$grade])
            ->values();
        $majors = $classes->map(fn (Classroom $class): string => $class->majorLabel())
            ->filter(fn (string $major): bool => $major !== '')
            ->unique()->sort()->values();
        $deleteClasses = Classroom::query()->withCount('students')->orderBy('nama')->get();
        $alumniCount = Student::query()->where('is_alumni', true)->count();
        $massDeleteOptions = [
            'class' => $deleteClasses
                ->filter(fn (Classroom $class): bool => $class->students_count > 0)
                ->map(fn (Classroom $class): array => ['value' => (string) $class->id, 'label' => $class->nama, 'count' => (int) $class->students_count])
                ->values(),
            'grade' => $deleteClasses
                ->filter(fn (Classroom $class): bool => $this->classroomGrade($class->nama) !== null)
                ->groupBy(fn (Classroom $class): ?string => $this->classroomGrade($class->nama))
                ->map(fn ($group, string $grade): array => ['value' => $grade, 'label' => 'Tingkat '.$grade, 'count' => (int) $group->sum('students_count')])
                ->filter(fn (array $option): bool => $option['count'] > 0)
                ->sortBy(fn (array $option): int => ['X' => 10, 'XI' => 11, 'XII' => 12][$option['value']])
                ->values(),
            'major' => $deleteClasses
                ->filter(fn (Classroom $class): bool => $class->majorLabel() !== '')
                ->groupBy(fn (Classroom $class): string => $class->majorLabel())
                ->map(fn ($group, string $major): array => ['value' => $major, 'label' => $major, 'count' => (int) $group->sum('students_count')])
                ->filter(fn (array $option): bool => $option['count'] > 0)
                ->sortBy('label')
                ->values(),
            'alumni' => collect($alumniCount > 0
                ? [['value' => 'all', 'label' => 'Semua siswa alumni', 'count' => $alumniCount]]
                : []),
        ];
        $editing = $request->filled('edit') ? Student::query()->find($request->integer('edit')) : null;
        $editingPhotoAvailable = $editing !== null && $this->studentPhotoPath($editing->induk) !== null;
        $attendanceDeleteMonth = (int) date('n');
        $attendanceDeleteYear = (int) date('Y');
        $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return view('students.index', compact('students', 'classes', 'grades', 'majors', 'majorGroups', 'availableRelatedMajors', 'massDeleteOptions', 'editing', 'editingPhotoAvailable', 'search', 'matchingStudentCount', 'showingAlumni', 'activeStudentCount', 'alumniStudentCount', 'attendanceDeleteMonth', 'attendanceDeleteYear', 'monthNames', 'photoDefaultClassroomId'));
    }

    public function storeMajorGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'majors' => ['required', 'array', 'min:1', 'max:8'],
            'majors.*' => ['nullable', 'string', 'max:50'],
        ]);
        $majorCatalog = app(MajorCatalog::class);
        $majorCatalog->createGroup($data['name'], $data['majors'], Classroom::query()->pluck('nama'));

        return redirect()->route('students.index')->with('status', 'Grup jurusan berhasil ditambahkan.');
    }

    public function updateMajorGroup(Request $request, string $majorGroup): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:50'],
            'majors' => ['required', 'array', 'min:1', 'max:8'],
            'majors.*' => ['nullable', 'string', 'max:50'],
        ]);
        if ($data['group_id'] !== $majorGroup) {
            throw ValidationException::withMessages([
                'major_group' => 'Identitas grup jurusan tidak cocok. Muat ulang halaman lalu coba kembali.',
            ]);
        }

        app(MajorCatalog::class)->updateGroup(
            $majorGroup,
            $data['name'],
            $data['majors'],
            Classroom::query()->pluck('nama')
        );

        return redirect()->route('students.index')->with('status', 'Grup jurusan berhasil diperbarui.');
    }

    public function destroyMajorGroup(string $majorGroup): RedirectResponse
    {
        app(MajorCatalog::class)->deleteGroup($majorGroup, Classroom::query()->pluck('nama'));

        return redirect()->route('students.index')->with('status', 'Grup jurusan berhasil dihapus.');
    }

    public function qrSelection(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('cari', ''));
        $students = Student::query()
            ->join('kelas as student_classrooms', 'student_classrooms.id', '=', 'siswa.id_kelas')
            ->select('siswa.*')
            ->where('siswa.is_alumni', false)
            ->with('classroom')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($studentQuery) use ($search): void {
                    $studentQuery->where('siswa.nama', 'like', '%'.$search.'%')
                        ->orWhere('siswa.induk', 'like', $search.'%')
                        ->orWhere('student_classrooms.nama', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('student_classrooms.nama')
            ->orderBy('siswa.nama')
            ->orderBy('siswa.id')
            ->simplePaginate(15)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('students._qr_selection_rows', compact('students'))->render(),
                'nextPageUrl' => $students->nextPageUrl(),
                'count' => $students->count(),
            ]);
        }

        return view('students.qr-selection', compact('students', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $uniqueInduk = Rule::unique('siswa', 'induk');
        if ($request->filled('id')) {
            $uniqueInduk->ignore($request->integer('id'));
        }

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:siswa,id'],
            'induk' => ['required', 'regex:/^\d+$/', 'max:30', $uniqueInduk],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'id_kelas' => ['required', 'integer', 'exists:kelas,id'],
            'kategori' => ['nullable', 'in:biasa,irma,nonis'],
            'is_alumni' => ['nullable', 'boolean'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hapus_foto' => ['nullable', 'boolean'],
        ], [
            'induk.regex' => 'Nomor induk harus berupa angka.',
            'induk.unique' => 'Nomor induk sudah digunakan oleh siswa lain.',
            'foto.file' => 'Foto gagal diterima server. Periksa upload_max_filesize dan folder sementara upload pada hosting.',
            'foto.max' => 'Ukuran foto maksimal 5 MiB sebelum dikompres.',
        ]);

        $student = isset($data['id']) ? Student::query()->findOrFail($data['id']) : new Student;
        $wasAlumni = $student->exists && $student->is_alumni;
        $previousInduk = $student->exists ? $student->induk : null;
        $isRekeyingPhoto = $previousInduk !== null
            && $previousInduk !== $data['induk']
            && ! $request->hasFile('foto')
            && ! $request->boolean('hapus_foto')
            && $this->studentPhotoPath($previousInduk) !== null;
        $category = $data['kategori'] ?? 'biasa';

        if ($request->hasFile('foto') || $request->boolean('hapus_foto') || $isRekeyingPhoto) {
            $otherStudent = Student::query()
                ->where('induk', $data['induk'])
                ->when($student->exists, fn ($query) => $query->where('id', '!=', $student->getKey()))
                ->exists();

            if ($otherStudent) {
                return back()->withInput()->withErrors(['induk' => 'Nomor induk harus unik untuk pengelolaan foto.']);
            }

            if ($isRekeyingPhoto && $this->studentPhotoPath($data['induk']) !== null) {
                return back()->withInput()->withErrors(['induk' => 'Foto untuk nomor induk baru sudah tersedia.']);
            }
        }

        $student->fill([
            'induk' => $data['induk'],
            'nama' => $data['nama'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'id_kelas' => $data['id_kelas'],
            'is_irma' => $category === 'irma',
            'is_nonis' => $category === 'nonis',
            'is_alumni' => array_key_exists('is_alumni', $data) ? (bool) $data['is_alumni'] : (bool) $student->is_alumni,
        ]);

        try {
            DB::transaction(function () use ($student, $request): void {
                $student->save();

                if ($request->hasFile('foto') && ! $this->saveStudentPhoto($request->file('foto'), $student->induk)) {
                    throw ValidationException::withMessages([
                        'foto' => 'Foto gagal diproses atau disimpan. Pastikan GD aktif dan storage/app dapat ditulis pada server.',
                    ]);
                }
            });
        } catch (QueryException) {
            return back()->withInput()->withErrors(['induk' => 'Nomor induk sudah terdaftar. Periksa data siswa yang sudah ada.']);
        }

        if ($request->hasFile('foto')) {
            if ($previousInduk !== null && $previousInduk !== $student->induk) {
                $this->deleteStudentPhoto($previousInduk);
            }
        } elseif ($request->boolean('hapus_foto')) {
            $this->deleteStudentPhoto($previousInduk ?? $student->induk);
        } elseif ($isRekeyingPhoto) {
            $this->moveStudentPhoto($previousInduk, $student->induk);
        }

        $statusMessage = $student->wasRecentlyCreated
            ? 'Siswa berhasil ditambahkan.'
            : ($wasAlumni && ! $student->is_alumni
                ? 'Siswa alumni berhasil diaktifkan kembali.'
                : 'Data siswa berhasil diperbarui.');

        return redirect()->route('students.index')->with('status', $statusMessage);
    }

    public function resetPortalPin(Student $student): RedirectResponse
    {
        $student->portal_pin = (string) config('app.student_default_pin', 'siswa123');
        $student->save();

        return redirect()->route('students.index')->with('status', 'PIN siswa '.$student->induk.' direset ke '.config('app.student_default_pin', 'siswa123').'. Siswa dapat menggantinya dari halaman Ubah PIN.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $induk = $student->induk;
        $student->delete();
        $photoFailures = $induk !== null && ! Student::query()->where('induk', $induk)->exists()
            ? $this->deleteStudentPhotos([$induk])
            : [];

        $redirect = redirect()->route('students.index')->with('status', 'Siswa dan absensinya berhasil dihapus.');
        if ($photoFailures !== []) {
            $redirect->with('warning', 'Data siswa terhapus, tetapi foto untuk '.count($photoFailures).' nomor induk gagal dihapus dari penyimpanan.');
        }

        return $redirect;
    }

    public function destroyMass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:class,grade,major,alumni'],
            'classroom_id' => ['required_if:scope,class', 'nullable', 'integer', 'exists:kelas,id'],
            'grade' => ['required_if:scope,grade', 'nullable', 'in:X,XI,XII'],
            'major' => ['required_if:scope,major', 'nullable', 'string', 'max:50'],
            'alumni' => ['required_if:scope,alumni', 'nullable', 'in:all'],
            'expected_count' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['scope'] === 'alumni') {
            $students = Student::query()->where('is_alumni', true)->get(['id', 'induk']);
        } else {
            $classrooms = Classroom::query()->get(['id', 'nama']);
            $classroomIds = match ($data['scope']) {
                'class' => [(int) $data['classroom_id']],
                'grade' => $classrooms
                    ->filter(fn (Classroom $class): bool => $this->classroomGrade($class->nama) === $data['grade'])
                    ->pluck('id')->all(),
                'major' => $classrooms
                    ->filter(fn (Classroom $class): bool => $class->majorLabel() === $data['major'])
                    ->pluck('id')->all(),
            };
            $students = Student::query()->whereIn('id_kelas', $classroomIds)->get(['id', 'induk']);
        }
        if ($students->count() !== (int) $data['expected_count']) {
            throw ValidationException::withMessages([
                'scope' => 'Jumlah siswa dalam kelompok berubah sejak halaman dibuka. Muat ulang halaman dan konfirmasi kembali.',
            ]);
        }

        $studentIds = $students->pluck('id')->all();
        $induks = $students->pluck('induk')->filter(fn ($induk): bool => is_string($induk) && $induk !== '')->unique()->values();
        DB::transaction(fn () => Student::query()->whereIn('id', $studentIds)->delete());

        $remainingInduks = Student::query()->whereIn('induk', $induks)->distinct()->pluck('induk');
        $photoInduks = $induks->diff($remainingInduks)->all();
        $photoFailures = $this->deleteStudentPhotos($photoInduks);

        $redirect = redirect()->route('students.index')
            ->with('status', $students->count().' siswa, absensi terkait, dan foto berhasil dihapus.');
        if ($photoFailures !== []) {
            $redirect->with('warning', 'Data siswa terhapus, tetapi foto untuk '.count($photoFailures).' nomor induk gagal dihapus dari penyimpanan.');
        }

        return $redirect;
    }

    public function bulkAlumniAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:delete,activate'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:siswa,id'],
        ]);

        $result = DB::transaction(function () use ($data): array {
            $students = Student::query()
                ->whereIn('id', $data['student_ids'])
                ->where('is_alumni', true)
                ->lockForUpdate()
                ->get(['id', 'induk']);

            if ($students->count() !== count($data['student_ids'])) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Pilihan harus berisi siswa alumni yang masih tersedia. Muat ulang halaman lalu pilih kembali.',
                ]);
            }

            if ($data['action'] === 'activate') {
                Student::query()
                    ->whereIn('id', $students->pluck('id'))
                    ->where('is_alumni', true)
                    ->update(['is_alumni' => false]);

                return ['count' => $students->count(), 'induks' => collect()];
            }

            Student::query()->whereIn('id', $students->pluck('id'))->delete();

            return [
                'count' => $students->count(),
                'induks' => $students->pluck('induk')
                    ->filter(fn ($induk): bool => is_string($induk) && $induk !== '')
                    ->unique()
                    ->values(),
            ];
        });

        if ($data['action'] === 'activate') {
            return redirect()->route('students.index', ['status' => 'alumni'])
                ->with('status', $result['count'].' siswa alumni berhasil dikembalikan menjadi siswa aktif.');
        }

        $remainingInduks = Student::query()
            ->whereIn('induk', $result['induks'])
            ->distinct()
            ->pluck('induk');
        $photoFailures = $this->deleteStudentPhotos($result['induks']->diff($remainingInduks)->all());

        $redirect = redirect()->route('students.index', ['status' => 'alumni'])
            ->with('status', $result['count'].' siswa alumni dan seluruh absensinya berhasil dihapus.');
        if ($photoFailures !== []) {
            $redirect->with('warning', 'Data siswa terhapus, tetapi foto untuk '.count($photoFailures).' nomor induk gagal dihapus dari penyimpanan.');
        }

        return $redirect;
    }

    public function storePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'induk' => ['required', 'regex:/^\d+$/', 'max:30', 'exists:siswa,induk'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'induk.regex' => 'Nomor induk harus berupa angka.',
            'foto.file' => 'Foto gagal diterima server. Periksa upload_max_filesize dan folder sementara upload pada hosting.',
            'foto.max' => 'Ukuran foto maksimal 5 MiB sebelum dikompres.',
        ]);

        $matches = Student::query()->where('induk', $data['induk'])->get();
        if ($matches->count() !== 1) {
            return back()->withInput()->withErrors(['induk' => 'Nomor induk harus mengarah ke tepat satu siswa.']);
        }

        $student = $matches->first();
        if (! $this->saveStudentPhoto($request->file('foto'), $student->induk)) {
            return back()->withInput()->withErrors(['foto' => 'Foto harus berhasil dikompres di bawah 100 KiB. Pastikan ekstensi GD aktif pada server.']);
        }

        return redirect()->route('students.qr', $student)->with('status', 'Foto siswa berhasil disimpan.');
    }

    public function storePhotosMass(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'photo_scope' => ['required', 'in:class,major_grade'],
            'photo_classroom_id' => ['required_if:photo_scope,class', 'nullable', 'integer', 'exists:kelas,id'],
            'photo_grade' => ['required_if:photo_scope,major_grade', 'nullable', 'in:X,XI,XII'],
            'photo_major' => ['required_if:photo_scope,major_grade', 'nullable', 'string', 'max:50'],
            'match_by' => ['required', 'in:induk,nama'],
            'fotos' => ['required', 'array', 'min:1', 'max:20'],
            'fotos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'fotos.max' => 'Pilih maksimal 20 foto per unggahan manual.',
            'fotos.required' => 'Tidak ada foto yang diterima. Periksa batas upload dan folder sementara PHP pada hosting.',
            'fotos.*.file' => 'Salah satu foto gagal diterima server. Periksa batas upload dan folder sementara PHP pada hosting.',
            'fotos.*.max' => 'Setiap foto maksimal 5 MiB sebelum dikompres.',
        ]);

        $classrooms = Classroom::query()->get(['id', 'nama']);
        if ($data['photo_scope'] === 'class') {
            $classroomIds = [(int) $data['photo_classroom_id']];
        } else {
            $classroomIds = $classrooms
                ->filter(fn (Classroom $class): bool => $class->majorLabel() === $data['photo_major']
                    && $this->classroomGrade($class->nama) === $data['photo_grade'])
                ->pluck('id')
                ->all();
            if ($classroomIds === []) {
                throw ValidationException::withMessages([
                    'photo_major' => 'Tidak ada rombel untuk jurusan dan angkatan yang dipilih. Muat ulang halaman lalu pilih lagi.',
                ]);
            }
        }

        $students = Student::query()->whereIn('id_kelas', $classroomIds)->get(['id', 'induk', 'nama']);
        $studentsByInduk = [];
        if ($data['match_by'] === 'induk') {
            foreach ($students as $student) {
                $studentsByInduk[trim((string) $student->induk)][] = $student;
            }
        }

        $savedStudentIds = [];
        $errors = [];
        $successCount = 0;
        $lastStudentId = null;

        foreach ($request->file('fotos', []) as $photo) {
            $filename = $photo->getClientOriginalName();
            $photoName = pathinfo($filename, PATHINFO_FILENAME);
            if ($data['match_by'] === 'induk') {
                $matches = $studentsByInduk[trim($photoName)] ?? [];
            } else {
                $key = $this->normalizeStudentPhotoName($photoName);
                $searchableName = preg_replace('/\s+/u', '', $key) ?? $key;
                if (mb_strlen($searchableName, 'UTF-8') < 3) {
                    $errors[] = $filename.': nama file terlalu pendek; gunakan minimal 3 karakter nama atau pilih pencocokan nomor induk.';
                    continue;
                }

                $matches = $students
                    ->filter(fn (Student $student): bool => $this->studentPhotoNameMatches((string) $student->nama, $key))
                    ->values()
                    ->all();

                if (count($matches) > 1) {
                    $matches = array_values(array_filter($matches, fn (Student $student): bool => $this->normalizeStudentPhotoName((string) $student->nama) === $key));
                }
            }

            if (count($matches) !== 1) {
                $errors[] = count($matches) === 0
                    ? $filename.': nama/nomor induk tidak ditemukan pada pilihan kelas atau jurusan/angkatan.'
                    : $filename.': cocok dengan lebih dari satu siswa; gunakan pencocokan nomor induk.';
                continue;
            }

            $student = $matches[0];
            if (isset($savedStudentIds[$student->id])) {
                $errors[] = $filename.': lebih dari satu foto mengarah ke siswa yang sama; hanya satu foto per siswa yang diunggah.';
                continue;
            }

            if (! $this->saveStudentPhoto($photo, $student->induk)) {
                $errors[] = $filename.': foto gagal dikompres di bawah 100 KiB atau gagal disimpan. Pastikan ekstensi GD aktif dan folder storage dapat ditulis.';
                continue;
            }

            $savedStudentIds[$student->id] = true;
            $lastStudentId = $student->id;
            $successCount++;
        }

        if ($request->expectsJson()) {
            $response = [
                'success' => $successCount > 0,
                'message' => $successCount > 0
                    ? 'Foto siswa berhasil diunggah.'
                    : ($errors[0] ?? 'Foto tidak berhasil diunggah.'),
                'student_id' => $lastStudentId,
            ];

            return response()->json($response, $successCount > 0 ? 200 : 422);
        }

        $redirect = redirect()->route('students.index');
        if ($successCount > 0) {
            $redirect->with('status', $successCount.' foto siswa berhasil diunggah.');
        }
        if ($errors !== []) {
            $details = implode(' ', array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $details .= ' Dan '.(count($errors) - 10).' foto lainnya gagal.';
            }
            $redirect->with('warning', $successCount > 0
                ? $successCount.' foto berhasil; '.count($errors).' foto gagal. '.$details
                : 'Tidak ada foto yang berhasil diunggah. '.$details);
        }

        return $redirect;
    }

    private function normalizeStudentPhotoName(string $name): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', trim($name)) ?? trim($name);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return mb_strtolower(trim($normalized), 'UTF-8');
    }

    private function studentPhotoNameMatches(string $studentName, string $photoName): bool
    {
        $normalizedStudentName = $this->normalizeStudentPhotoName($studentName);
        $normalizedPhotoName = $this->normalizeStudentPhotoName($photoName);

        if ($normalizedStudentName === '' || $normalizedPhotoName === '') {
            return false;
        }

        $studentWords = preg_split('/\s+/u', $normalizedStudentName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $photoWords = preg_split('/\s+/u', $normalizedPhotoName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($studentWords === [] || $photoWords === []) {
            return false;
        }

        if ($normalizedStudentName === $normalizedPhotoName) {
            return true;
        }

        if (str_contains($normalizedStudentName, $normalizedPhotoName) || str_contains($normalizedPhotoName, $normalizedStudentName)) {
            return true;
        }

        $studentNoSpace = implode('', $studentWords);
        $photoNoSpace = implode('', $photoWords);
        if ($photoNoSpace !== '' && (str_contains($studentNoSpace, $photoNoSpace) || str_contains($photoNoSpace, $studentNoSpace))) {
            return true;
        }

        $matchedPhotoWords = 0;
        foreach ($photoWords as $photoWord) {
            if (mb_strlen($photoWord, 'UTF-8') < 2) {
                return false;
            }

            $wordMatched = false;
            foreach ($studentWords as $studentWord) {
                if ($studentWord === $photoWord) {
                    $wordMatched = true;
                    break;
                }

                if (mb_strlen($studentWord, 'UTF-8') < 2 || mb_strlen($photoWord, 'UTF-8') < 2) {
                    continue;
                }

                if (
                    str_contains($studentWord, $photoWord)
                    || str_contains($photoWord, $studentWord)
                    || str_starts_with($studentWord, $photoWord)
                    || str_starts_with($photoWord, $studentWord)
                ) {
                    $wordMatched = true;
                    break;
                }
            }

            if ($wordMatched) {
                $matchedPhotoWords++;
            }
        }

        if ($matchedPhotoWords === count($photoWords) && $matchedPhotoWords >= min(2, count($photoWords))) {
            return true;
        }

        $photoIndex = 0;
        foreach ($studentWords as $studentWord) {
            if ($photoIndex >= count($photoWords)) {
                break;
            }

            $photoWord = $photoWords[$photoIndex];
            if ($studentWord === $photoWord || str_starts_with($studentWord, $photoWord) || str_starts_with($photoWord, $studentWord)) {
                $photoIndex++;
            }
        }

        return $photoIndex === count($photoWords) && $photoIndex >= min(2, count($studentWords));
    }

    public function qr(Student $student): View
    {
        $student->load('classroom');

        return view('students.qr', [
            'student' => $student,
            'photoAvailable' => $this->studentPhotoPath($student->induk) !== null,
            'templateDataUri' => $this->templateStorage->dataUri(),
        ]);
    }

    public function photo(Student $student): BinaryFileResponse
    {
        $path = $this->studentPhotoPath($student->induk);
        abort_if($path === null, 404);

        $disk = Storage::disk('local');

        return response()->file($disk->path($path), ['Cache-Control' => 'private, no-store']);
    }

    public function qrSvg(Student $student): Response
    {
        return response($this->studentQrSvg($student->induk), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function qrHaidSvg(Student $student): Response
    {
        abort_unless($student->jenis_kelamin === 'P', 404);

        return response(app(StudentQrCodeGenerator::class)->svgForHaid($student->induk), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function qrPdf(Student $student): Response
    {
        $student->load('classroom');
        return $this->downloadQrPdf([$this->qrPdfCard($student)], 'QR-'.$student->induk.'.pdf');
    }

    public function qrPdfBatch(Request $request): Response
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:siswa,id'],
        ]);
        $students = Student::query()->with('classroom')->whereIn('id', $data['student_ids'])->get()->keyBy('id');
        if ($students->count() !== count($data['student_ids'])) {
            throw ValidationException::withMessages(['student_ids' => 'Pilihan berisi siswa yang tidak dapat dicetak. Muat ulang daftar, lalu pilih kembali.']);
        }
        $cards = [];

        foreach ($data['student_ids'] as $studentId) {
            $student = $students->get((int) $studentId);
            if ($student !== null) {
                $cards[] = $this->qrPdfCard($student);
            }
        }

        abort_if($cards === [], 404);

        return $this->downloadBatchQrPdf($cards, 'Kartu-Siswa-Terpilih.pdf');
    }

    public function qrPdfMass(Request $request): Response
    {
        $data = $request->validate([
            'scope' => ['required', 'in:class,grade,major'],
            'classroom_id' => ['required_if:scope,class', 'nullable', 'integer', 'exists:kelas,id'],
            'grade' => ['required_if:scope,grade', 'nullable', 'in:X,XI,XII'],
            'major' => ['required_if:scope,major', 'nullable', 'string', 'max:50'],
        ]);

        $classrooms = Classroom::query()->get(['id', 'nama']);
        $classroomIds = match ($data['scope']) {
            'class' => [(int) $data['classroom_id']],
            'grade' => $classrooms
                ->filter(fn (Classroom $class): bool => $this->classroomGrade($class->nama) === $data['grade'])
                ->pluck('id')->all(),
            'major' => $classrooms
                ->filter(fn (Classroom $class): bool => $class->majorLabel() === $data['major'])
                ->pluck('id')->all(),
        };

        $students = Student::query()
            ->join('kelas as student_classrooms', 'student_classrooms.id', '=', 'siswa.id_kelas')
            ->select('siswa.*')
            ->where('siswa.is_alumni', false)
            ->whereIn('siswa.id_kelas', $classroomIds)
            ->with('classroom')
            ->orderBy('student_classrooms.nama')
            ->orderBy('siswa.nama')
            ->orderBy('siswa.id')
            ->limit(101)
            ->get();

        if ($students->isEmpty()) {
            throw ValidationException::withMessages(['scope' => 'Tidak ada siswa aktif untuk pilihan tersebut.']);
        }

        if ($students->count() > 100) {
            throw ValidationException::withMessages(['scope' => 'Pilihan ini berisi lebih dari 100 siswa. Persempit pilihan atau gunakan menu pilihan manual.']);
        }

        $cards = $students->map(fn (Student $student): array => $this->qrPdfCard($student))->all();
        $label = match ($data['scope']) {
            'class' => $classrooms->firstWhere('id', (int) $data['classroom_id'])?->nama,
            'grade' => 'Kelas-'.$data['grade'],
            'major' => $data['major'],
        };
        $filenameLabel = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $label), '-');

        return $this->downloadBatchQrPdf($cards, 'Kartu-Siswa-'.$filenameLabel.'.pdf');
    }

    private function classroomGrade(string $name): ?string
    {
        return (new Classroom(['nama' => $name]))->gradeLabel();
    }

    private function qrPdfCard(Student $student): array
    {
        $student->loadMissing('classroom');
        $templateDataUri = $this->templateStorage->dataUri();
        $photoPath = $this->studentPhotoPath($student->induk);
        $photoDataUri = null;

        if ($photoPath !== null) {
            $extension = pathinfo($photoPath, PATHINFO_EXTENSION);
            $mimeType = $extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension;
            $photoDataUri = $this->photoProcessor->coverDataUri(
                Storage::disk('local')->get($photoPath),
                $mimeType,
                500,
                $templateDataUri !== null ? 597 : 637
            );
        }

        $qrDataUri = 'data:image/svg+xml;base64,'.base64_encode($this->studentQrSvg($student->induk));
        return compact('student', 'photoDataUri', 'qrDataUri', 'templateDataUri');
    }

    private function downloadQrPdf(array $cards, string $filename): Response
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('students.qr-pdf', $cards[0])->render(), 'UTF-8');
        $pdf->setPaper([
            0,
            0,
            (self::CARD_WIDTH_MM * 2 + self::CARD_FOLD_GAP_MM + 2) * 72 / 25.4,
            (self::CARD_HEIGHT_MM + 2) * 72 / 25.4,
        ]);
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function downloadBatchQrPdf(array $cards, string $filename): Response
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('students.qr-batch-pdf', compact('cards'))->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function studentQrSvg(string $induk): string
    {
        return app(StudentQrCodeGenerator::class)->svg($induk);
    }

    private function studentPhotoPath(string $induk): ?string
    {
        return $this->studentPhotoPaths($induk)[0] ?? null;
    }

    private function saveStudentPhoto(UploadedFile $photo, string $induk): bool
    {
        $disk = Storage::disk('local');
        $directory = $this->studentPhotoDirectory($induk);
        $optimized = $this->photoProcessor->optimizeForStorage(
            $photo->getContent(),
            $photo->extension()
        );
        if ($optimized === null) {
            return false;
        }

        $path = $directory.'/'.$induk.'.'.$optimized['extension'];

        $disk->makeDirectory($directory);
        if (! $disk->put($path, $optimized['contents'])) {
            return false;
        }

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            foreach ([$directory, 'foto-siswa'] as $oldDirectory) {
                $oldPath = $oldDirectory.'/'.$induk.'.'.$extension;
                if ($oldPath !== $path) {
                    $disk->delete($oldPath);
                }
            }
        }

        return true;
    }

    private function deleteStudentPhoto(string $induk): void
    {
        $path = $this->studentPhotoPath($induk);
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }

    private function deleteStudentPhotos(array $induces): array
    {
        $disk = Storage::disk('local');
        $failed = [];

        foreach ($induces as $induk) {
            foreach ($this->studentPhotoPaths($induk) as $path) {
                if (! $disk->delete($path)) {
                    $failed[] = $induk;
                }
            }
        }

        return array_values(array_unique($failed));
    }

    private function studentPhotoPaths(string $induk): array
    {
        $disk = Storage::disk('local');
        $paths = [];

        foreach (array_unique([$this->studentPhotoDirectory($induk), 'foto-siswa']) as $directory) {
            if (! $disk->directoryExists($directory)) {
                continue;
            }

            foreach ($disk->files($directory) as $path) {
                if (preg_match('/^'.preg_quote($induk, '/').'\.(?:jpe?g|png|webp)$/i', basename($path)) === 1) {
                    $paths[] = $path;
                }
            }
        }

        sort($paths);

        return $paths;
    }

    private function moveStudentPhoto(string $oldInduk, string $newInduk): void
    {
        $path = $this->studentPhotoPath($oldInduk);
        if ($path !== null) {
            $directory = $this->studentPhotoDirectory($newInduk);
            $newPath = $directory.'/'.$newInduk.'.'.pathinfo($path, PATHINFO_EXTENSION);
            $disk = Storage::disk('local');
            $disk->makeDirectory($directory);
            $disk->move($path, $newPath);
        }
    }

    private function studentPhotoDirectory(string $induk): string
    {
        return 'foto-siswa/'.substr($induk, 0, 3);
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:50', 'unique:kelas,nama']]);
        Classroom::query()->create($data);

        return redirect()->route('students.index')->with('status', 'Rombel berhasil ditambahkan.');
    }

    public function destroyClass(Classroom $classroom): RedirectResponse
    {
        try {
            $classroom->delete();
        } catch (QueryException) {
            return back()->withErrors(['kelas' => 'Rombel tidak dapat dihapus selama masih memiliki siswa.']);
        }

        return redirect()->route('students.index')->with('status', 'Rombel berhasil dihapus.');
    }
}
