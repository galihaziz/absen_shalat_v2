<?php

namespace App\Http\Controllers;

use AbsenShalat\Models\Student;
use App\Services\StudentCardPhotoProcessor;
use App\Services\StudentCardTemplateStorage;
use App\Services\StudentQrCodeGenerator;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StudentPortalController extends Controller
{
    private const CARD_WIDTH_MM = 53.98;

    private const CARD_HEIGHT_MM = 85.6;

    public function __construct(
        private readonly StudentQrCodeGenerator $qrCodeGenerator,
        private readonly StudentCardPhotoProcessor $photoProcessor,
        private readonly StudentCardTemplateStorage $templateStorage
    ) {
    }

    public function create(Request $request): View|RedirectResponse
    {
        $studentDomain = strtolower((string) config('app.student_domain'));
        $isStudentDomain = $studentDomain !== '' && strtolower($request->getHost()) === $studentDomain;

        if ($isStudentDomain && $request->path() !== '/') {
            return redirect('/');
        }

        $loginAction = $request->path() === '/' ? url('/') : route('student.portal.login.store');

        return view('student-portal.login', compact('loginAction'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'induk' => ['required', 'regex:/^\d+$/', 'max:30'],
            'pin' => ['required', 'string', 'max:64'],
        ], ['induk.regex' => 'Nomor induk harus berupa angka.']);
        $students = Student::query()->where('induk', $data['induk'])->limit(2)->get();
        $student = $students->count() === 1 ? $students->first() : null;

        if ($student === null || ! Hash::check($data['pin'], (string) $student->portal_pin)) {
            return back()->withInput()->withErrors(['induk' => 'Nomor induk atau PIN tidak cocok.']);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('student_portal_id', $student->id);
        $request->session()->regenerate();

        return redirect()->route('student.portal.card');
    }

    public function card(Request $request): View
    {
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');
        return view('student-portal.card', $this->studentCardData($student));
    }

    public function editPin(Request $request): View
    {
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');

        return view('student-portal.pin', compact('student'));
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'string', 'max:64'],
            'pin' => [
                'required',
                'string',
                'min:8',
                'max:64',
                'confirmed',
                'different:current_pin',
                Rule::notIn([(string) config('app.student_default_pin', 'siswa123')]),
            ],
        ]);
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');

        if (! Hash::check($data['current_pin'], (string) $student->portal_pin)) {
            return back()->withErrors(['current_pin' => 'PIN saat ini tidak cocok.']);
        }

        $student->portal_pin = $data['pin'];
        $student->save();
        $request->session()->regenerate();

        return redirect()->route('student.portal.pin.edit')->with('status', 'PIN berhasil diubah.');
    }

    public function pdf(Request $request): Response
    {
        /** @var Student $student */
        $student = $request->attributes->get('studentPortalStudent');
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('students.qr-pdf', $this->studentCardData($student, true))->render(), 'UTF-8');
        $pdf->setPaper([
            0,
            0,
            self::CARD_WIDTH_MM * 72 / 25.4,
            self::CARD_HEIGHT_MM * 72 / 25.4,
        ]);
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Kartu-'.$student->induk.'.pdf"',
        ]);
    }

    private function studentCardData(Student $student, bool $forPdf = false): array
    {
        $templateDataUri = $this->templateStorage->dataUri();
        $photoPath = $this->studentPhotoPath($student->induk);
        $photoDataUri = null;

        if ($photoPath !== null) {
            $extension = pathinfo($photoPath, PATHINFO_EXTENSION);
            $mimeType = $extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension;
            $photoContents = Storage::disk('local')->get($photoPath);
            $photoDataUri = $forPdf
                ? $this->photoProcessor->coverDataUri($photoContents, $mimeType, 500, $templateDataUri !== null ? 597 : 637)
                : 'data:'.$mimeType.';base64,'.base64_encode($photoContents);
        }

        $qrDataUri = 'data:image/svg+xml;base64,'.base64_encode($this->qrCodeGenerator->svg($student->induk));
        $templateCardHeightMm = ! $forPdf && $templateDataUri !== null ? self::CARD_HEIGHT_MM : null;

        return compact('student', 'photoDataUri', 'qrDataUri', 'templateDataUri', 'templateCardHeightMm');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function studentPhotoPath(string $induk): ?string
    {
        $disk = Storage::disk('local');

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            foreach (['foto-siswa/'.substr($induk, 0, 3), 'foto-siswa'] as $directory) {
                $path = $directory.'/'.$induk.'.'.$extension;
                if ($disk->exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}