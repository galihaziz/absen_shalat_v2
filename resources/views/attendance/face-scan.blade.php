@extends('layouts.app')

@section('title', 'Scan Wajah · Rekap Absensi Solat')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">{{ $isAdminRegistration ? 'DATA SISWA' : 'KEHADIRAN HARI INI' }}</p>
        <h1>{{ $isAdminRegistration ? 'Registrasi wajah siswa' : 'Scan Wajah' }}</h1>
        @if($student)
            <p>{{ $student->nama }} · Nomor induk {{ $student->induk }} · {{ $student->classroom->nama }}</p>
        @else
            <p>Verifikasi wajah sebagai alternatif scan QR. Kamera diproses di perangkat ini.</p>
        @endif
    </div>
</div>
@if(session('status'))
    <div class="notice success" role="status">{{ session('status') }}</div>
@endif

<section
    class="panel face-scan-panel"
    id="faceScanner"
    data-attendance-endpoint="{{ $attendanceEndpoint ?? '' }}"
    data-registration-endpoint="{{ $registrationEndpoint ?? '' }}"
    data-admin-registration="{{ $isAdminRegistration ? 'true' : 'false' }}"
    data-student-induk="{{ $student?->induk ?? '' }}"
>
    @if($student)
        <p class="qr-scan-date">Siswa: {{ $student->nama }} · Nomor induk {{ $student->induk }}</p>
        @if($student->faceRegistration)
            <div class="actions face-registration-management">
                <span>Wajah sudah terdaftar. Kode unik wajah: <code>{{ $student->faceRegistration->face_code }}</code></span>
                @if($isAdminRegistration)
                    <form method="post" action="{{ route('students.face.delete', $student) }}" onsubmit="return confirm('Hapus data wajah {{ $student->nama }}?')">
                        @csrf @method('DELETE')
                        <button class="button danger small" type="submit">Hapus wajah</button>
                    </form>
                @else
                    <form method="post" action="{{ route('student.portal.face.delete') }}" onsubmit="return confirm('Hapus data wajah akun ini?')">
                        @csrf @method('DELETE')
                        <button class="button danger small" type="submit">Hapus wajah saya</button>
                    </form>
                @endif
            </div>
        @endif
    @endif

    @if($attendanceEndpoint && $registrationEndpoint)
        <div class="actions face-scan-modes" role="group" aria-label="Mode scan wajah">
            <button class="button primary" type="button" data-face-mode="attendance" aria-pressed="true">Presensi</button>
            <button class="button" type="button" data-face-mode="registration" aria-pressed="false">Daftarkan wajah</button>
        </div>
    @endif
    @if(! $student && $attendanceEndpoint)
        <div class="actions face-scan-modes face-attendance-types" role="group" aria-label="Jenis presensi wajah">
            <button class="button primary" type="button" data-attendance-type="umum" aria-pressed="true">Wajah Umum</button>
            <button class="button" type="button" data-attendance-type="haid" aria-pressed="false">Wajah Haid</button>
        </div>
        <p id="faceAttendanceTypeHint" class="qr-scan-mode-hint">Mode Umum: catat kehadiran siswa.</p>
    @endif

    <div class="face-camera-layout">
        <div class="face-camera-column">
            <div class="face-camera-viewport">
                <video id="faceCameraVideo" autoplay muted playsinline aria-label="Kamera scan wajah"></video>
                <canvas id="faceCameraOverlay" aria-hidden="true"></canvas>
                <div class="face-detection-guide" aria-hidden="true"><span>Posisikan wajah di sini</span></div>
                <div class="face-camera-placeholder" id="faceCameraPlaceholder">
                    <strong>Kamera akan dibuka otomatis</strong>
                    <span>Izinkan akses kamera saat browser meminta. Presensi akan dikirim otomatis setelah wajah terdeteksi.</span>
                </div>
            </div>
            <div class="actions face-camera-actions">
                <button class="button primary" id="faceCameraToggle" type="button">Coba buka kamera</button>
                <button class="button" id="faceCameraStop" type="button" disabled>Matikan kamera</button>
            </div>
        </div>
        <div class="face-camera-feedback">
            <p class="eyebrow">STATUS</p>
            <p id="faceScanStatus" role="status" aria-live="polite">Siap menggunakan scan wajah.</p>
            <div class="face-registration-fields" id="faceRegistrationFields" hidden>
                <p>Ikuti instruksi di bawah. Tahan setiap pose sampai wajah terdeteksi stabil; pendaftaran tersimpan otomatis setelah semua pose selesai. Nomor induk diambil dari akun siswa.</p>
                <p id="facePoseStep" class="face-pose-step" aria-live="polite">Siap memulai.</p>
                <p id="faceSampleCount">0 dari 5 pose</p>
                <div class="face-pose-progress" aria-hidden="true"><span id="facePoseProgress"></span></div>
                <p id="facePoseFeedback" role="status" aria-live="polite">Ikuti arahan posisi wajah.</p>
                <button class="button primary" id="faceRegistrationRetry" type="button" hidden>Coba simpan kembali</button>
            </div>
            <div id="faceScanResult" class="face-scan-result" role="status" aria-live="polite" hidden></div>
            <p class="face-privacy-note">Gambar video tidak dikirim ke server. Sistem hanya menyimpan representasi numerik wajah terenkripsi.</p>
        </div>
    </div>
</section>
@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="{{ asset('js/face-scanner.js') }}?v={{ md5_file(public_path('js/face-scanner.js')) }}" defer></script>
@endpush
@endsection
