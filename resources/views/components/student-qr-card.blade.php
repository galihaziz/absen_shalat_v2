@props(['student', 'photoSrc' => null, 'qrSrc', 'pdf' => false, 'templateSrc' => null, 'templateCardHeightMm' => null])

<div class="qr-student-card{{ $pdf ? ' qr-student-card-pdf' : '' }}{{ $templateSrc ? ' qr-student-card--with-template' : '' }}" style="{{ ! $pdf && $templateSrc && $templateCardHeightMm ? 'height: '.$templateCardHeightMm.'mm;' : '' }}">
    @if($templateSrc)
        <img class="qr-student-card-template" src="{{ $templateSrc }}" alt="" aria-hidden="true">
    @endif
    <div class="qr-student-card-header">
        <span class="qr-student-card-brand">REKAP ABSENSI SOLAT</span>
        <span class="qr-student-card-type">KARTU SISWA</span>
    </div>
    <div class="qr-student-photo-frame">
        @if($photoSrc)
            <div class="qr-student-photo-wrap">
                <img class="qr-student-photo" src="{{ $photoSrc }}" alt="Foto {{ $student->nama }}">
            </div>
        @else
            <div class="qr-student-photo-placeholder" role="img" aria-label="Foto siswa belum tersedia">Foto belum tersedia</div>
        @endif
    </div>
    <div class="qr-student-details">
        <h2>{{ $student->nama }}</h2>
        <p><strong>{{ $student->induk }}</strong></p>
        <p><strong>{{ $student->classroom->majorLabel() }}</strong></p>
    </div>
    <div class="qr-student-code-frame">
        <img class="qr-student-code" src="{{ $qrSrc }}" alt="QR code siswa {{ $student->nama }} dengan nomor induk {{ $student->induk }}">
    </div>
</div>