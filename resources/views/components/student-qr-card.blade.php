@props(['student', 'photoSrc' => null, 'qrSrc', 'pdf' => false, 'templateSrc' => null, 'side' => 'both'])

<div class="qr-student-card-set{{ $pdf ? ' qr-student-card-set-pdf' : '' }}">
    @if(in_array($side, ['both', 'front'], true))
        <section class="qr-student-card qr-student-card--front{{ $templateSrc ? ' qr-student-card--with-template' : '' }}">
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
        </section>
        @if($side === 'both')
            <div class="qr-student-card-spacer" aria-hidden="true"></div>
        @endif
    @endif

    @if(in_array($side, ['both', 'back'], true))
        <section class="qr-student-card qr-student-card--back">
            <div class="qr-student-card-cutline" aria-hidden="true">
                <span class="qr-student-card-cutline-top"></span>
                <span class="qr-student-card-cutline-right"></span>
                <span class="qr-student-card-cutline-bottom"></span>
                <span class="qr-student-card-cutline-left"></span>
            </div>
            <div class="qr-student-back-details">
                <h2>{{ $student->nama }}</h2>
                <p><strong>{{ $student->induk }}</strong></p>
                <p><strong>{{ $student->classroom->majorLabel() }}</strong></p>
            </div>
            <div class="qr-student-code-frame">
                <img class="qr-student-code" src="{{ $qrSrc }}" alt="QR code siswa {{ $student->nama }} dengan nomor induk {{ $student->induk }}">
            </div>
        </section>
    @endif
</div>