<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Classroom;
use AbsenShalat\Models\Student;
use App\Services\StudentCardPhotoProcessor;
use App\Services\StudentCardTemplateStorage;
use App\Services\StudentQrCodeGenerator;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    private const CARD_WIDTH_MM = 53.98;

    private const CARD_HEIGHT_MM = 85.6;

    public function __construct(
        private readonly StudentCardPhotoProcessor $photoProcessor,
        private readonly StudentCardTemplateStorage $templateStorage
    )
    {
    }

    public function index(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('cari', ''));
        $studentQuery = Student::query()
            ->join('kelas as student_classrooms', 'student_classrooms.id', '=', 'siswa.id_kelas')
            ->select('siswa.*')
            ->where('is_alumni', false)
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
            ->simplePaginate(50)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('students._rows', compact('students', 'search'))->render(),
                'nextPageUrl' => $students->nextPageUrl(),
                'count' => $students->count(),
            ]);
        }

        $classes = Classroom::query()
            ->withCount(['students' => fn ($query) => $query->where('is_alumni', false)])
            ->orderBy('nama')->get();
        $grades = $classes->map(fn (Classroom $class): ?string => $this->classroomGrade($class->nama))
            ->filter()->unique()
            ->sortBy(fn (string $grade): int => ['X' => 10, 'XI' => 11, 'XII' => 12][$grade])
            ->values();
        $majors = $classes->map(fn (Classroom $class): string => $class->majorLabel())
            ->unique()->sort()->values();
        $deleteClasses = Classroom::query()->withCount('students')->orderBy('nama')->get();
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
                ->groupBy(fn (Classroom $class): string => $class->majorLabel())
                ->map(fn ($group, string $major): array => ['value' => $major, 'label' => $major, 'count' => (int) $group->sum('students_count')])
                ->filter(fn (array $option): bool => $option['count'] > 0)
                ->sortBy('label')
                ->values(),
        ];
        $editing = $request->filled('edit') ? Student::query()->find($request->integer('edit')) : null;
        $editingPhotoAvailable = $editing !== null && $this->studentPhotoPath($editing->induk) !== null;
        $attendanceDeleteMonth = (int) date('n');
        $attendanceDeleteYear = (int) date('Y');
        $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return view('students.index', compact('students', 'classes', 'grades', 'majors', 'massDeleteOptions', 'editing', 'editingPhotoAvailable', 'search', 'matchingStudentCount', 'attendanceDeleteMonth', 'attendanceDeleteYear', 'monthNames'));
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
            ->simplePaginate(50)
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
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:siswa,id'],
            'induk' => ['required', 'regex:/^\d+$/', 'max:30'],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'id_kelas' => ['required', 'integer', 'exists:kelas,id'],
            'kategori' => ['nullable', 'in:biasa,irma,nonis'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hapus_foto' => ['nullable', 'boolean'],
        ], ['induk.regex' => 'Nomor induk harus berupa angka.']);

        $student = isset($data['id']) ? Student::query()->findOrFail($data['id']) : new Student;
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
        ]);

        try {
            $student->save();
        } catch (QueryException) {
            return back()->withInput()->withErrors(['nama' => 'Siswa dengan nama, jenis kelamin, dan kelas yang sama sudah terdaftar.']);
        }

        if ($request->hasFile('foto')) {
            if (! $this->saveStudentPhoto($request->file('foto'), $student->induk)) {
                return back()->withInput()->withErrors(['foto' => 'Foto harus berhasil dikompres di bawah 100 KiB. Pastikan ekstensi GD aktif pada server.']);
            }

            if ($previousInduk !== null && $previousInduk !== $student->induk) {
                $this->deleteStudentPhoto($previousInduk);
            }
        } elseif ($request->boolean('hapus_foto')) {
            $this->deleteStudentPhoto($previousInduk ?? $student->induk);
        } elseif ($isRekeyingPhoto) {
            $this->moveStudentPhoto($previousInduk, $student->induk);
        }

        return redirect()->route('students.index')->with('status', $student->wasRecentlyCreated ? 'Siswa berhasil ditambahkan.' : 'Data siswa berhasil diperbarui.');
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
            'scope' => ['required', 'in:class,grade,major'],
            'classroom_id' => ['required_if:scope,class', 'nullable', 'integer', 'exists:kelas,id'],
            'grade' => ['required_if:scope,grade', 'nullable', 'in:X,XI,XII'],
            'major' => ['required_if:scope,major', 'nullable', 'string', 'max:50'],
            'expected_count' => ['required', 'integer', 'min:1'],
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
        $students = Student::query()->whereIn('id_kelas', $classroomIds)->get(['id', 'induk']);
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

    public function storePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'induk' => ['required', 'regex:/^\d+$/', 'max:30', 'exists:siswa,induk'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], ['induk.regex' => 'Nomor induk harus berupa angka.']);

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

    public function qr(Student $student): View
    {
        $student->load('classroom');

        return view('students.qr', [
            'student' => $student,
            'photoAvailable' => $this->studentPhotoPath($student->induk) !== null,
            'templateDataUri' => $this->templateStorage->dataUri(),
            'templateCardHeightMm' => self::CARD_HEIGHT_MM,
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
            self::CARD_WIDTH_MM * 72 / 25.4,
            self::CARD_HEIGHT_MM * 72 / 25.4,
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
        $pdf->setPaper('A4', 'portrait');
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
        $disk = Storage::disk('local');

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            foreach ([$this->studentPhotoDirectory($induk), 'foto-siswa'] as $directory) {
                $path = $directory.'/'.$induk.'.'.$extension;
                if ($disk->exists($path)) {
                    return $path;
                }
            }
        }

        return null;
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

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            foreach ([$this->studentPhotoDirectory($induk), 'foto-siswa'] as $directory) {
                $path = $directory.'/'.$induk.'.'.$extension;
                if ($disk->exists($path)) {
                    $paths[] = $path;
                }
            }
        }

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
