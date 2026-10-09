@props(['student', 'qrSrc', 'eyebrow' => 'QR SISWA', 'title' => 'QR terpisah', 'description' => null])

<section class="panel qr-standalone-panel">
    <div class="qr-standalone-content">
        <div>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h2>{{ $title }}</h2>
            <p class="qr-standalone-induk">{{ $description ?? 'Nomor induk '.$student->induk }}</p>
        </div>
        <img class="qr-standalone-image" src="{{ $qrSrc }}" alt="{{ $title }} {{ $student->nama }}">
    </div>
</section>