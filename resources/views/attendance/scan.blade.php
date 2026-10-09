@extends('layouts.app')

@section('title', 'Scan QR Kehadiran · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">KEHADIRAN HARI INI</p><h1>Scan QR</h1></div></div>
<section class="panel qr-scan-panel" id="qrScanner" data-endpoint="{{ route('attendance.scan.record') }}">
    <p class="qr-scan-date">Tanggal scan: <time datetime="{{ $scanDate->toDateString() }}">{{ $scanDate->format('d/m/Y') }}</time></p>
    @if($scanStartTime && $scanEndTime)<p class="qr-scan-cutoff">Jam scan hari ini: {{ $scanStartTime }}–{{ $scanEndTime }}</p>@endif
    <div class="actions qr-scan-modes" role="group" aria-label="Mode pemindaian QR">
        <button class="button primary" type="button" data-scan-mode="umum" aria-pressed="true">QR Umum</button>
        <button class="button" type="button" data-scan-mode="haid" aria-pressed="false">QR Haid</button>
    </div>
    <p class="qr-scan-mode-hint" id="qrScanModeHint">Mode Umum: QR siswa mencatat kehadiran.</p>
    <div class="qr-scan-layout">
        <div class="qr-scan-camera-column">
            <div class="qr-scan-viewport">
                <video id="qrScanVideo" muted playsinline aria-label="Kamera pemindai QR"></video>
                <div class="qr-scan-frame" aria-hidden="true"></div>
            </div>
            <div class="actions qr-scan-actions">
                <button class="button primary" id="qrScanStart" type="button" @disabled($scanClosed)>Mulai Scan</button>
                <button class="button" id="qrScanStop" type="button" disabled>Hentikan</button>
            </div>
        </div>
        <div class="qr-scan-feedback">
            <p class="eyebrow">STATUS</p>
            <p id="qrScanStatus" role="status" aria-live="polite">{{ $scanClosed ? 'Waktu scan tersedia pukul '.$scanStartTime.' sampai '.$scanEndTime.'.' : 'Siap memindai QR hari ini.' }}</p>
            <p class="qr-last-scan" id="qrLastScan" hidden></p>
            <div id="qrScanResult" class="qr-scan-result" hidden>
                <h2 id="qrStudentName"></h2>
                <p id="qrStudentInduk"></p>
                <p id="qrStudentClass"></p>
                <p id="qrStudentScanDate"></p>
                <strong id="qrAttendanceMessage"></strong>
            </div>
        </div>
    </div>
</section>
@push('scripts')
<script src="{{ asset('js/qr-scanner.js') }}?v={{ md5_file(public_path('js/qr-scanner.js')) }}" defer></script>
<script>
const qrScannerPanel = document.getElementById('qrScanner');
const qrScanModeButtons = Array.from(qrScannerPanel.querySelectorAll('[data-scan-mode]'));
const qrScanModeHint = document.getElementById('qrScanModeHint');
window.qrAttendanceScanMode = 'umum';

const browserFetch = window.fetch.bind(window);
window.fetch = (input, options = {}) => {
    const requestUrl = typeof input === 'string' ? input : input.url;
    if (requestUrl !== qrScannerPanel.dataset.endpoint || typeof options.body !== 'string') {
        return browserFetch(input, options);
    }

    const payload = JSON.parse(options.body);
    if (window.qrAttendanceScanMode === 'haid') {
        payload.jenis = 'haid';
    } else {
        delete payload.jenis;
    }

    return browserFetch(input, {...options, body: JSON.stringify(payload)});
};

qrScanModeButtons.forEach((button) => button.addEventListener('click', () => {
    window.qrAttendanceScanMode = button.dataset.scanMode;
    qrScanModeButtons.forEach((modeButton) => {
        const selected = modeButton === button;
        modeButton.setAttribute('aria-pressed', String(selected));
        modeButton.classList.toggle('primary', selected);
    });
    qrScanModeHint.textContent = window.qrAttendanceScanMode === 'haid'
        ? 'Mode Haid: pindai QR siswi untuk mencatat keterangan Haid.'
        : 'Mode Umum: QR siswa mencatat kehadiran.';
}));

const scanTimestamp = document.getElementById('qrStudentScanDate');
const lastScanLabel = document.getElementById('qrLastScan');
const scanStatus = document.getElementById('qrScanStatus');
new MutationObserver(() => {
    if (!scanTimestamp.textContent.trim()) return;
    lastScanLabel.textContent = `Scan berhasil terakhir: ${scanTimestamp.textContent.replace('Tanggal ', '')}`;
    lastScanLabel.hidden = false;
}).observe(scanTimestamp, {childList: true, characterData: true, subtree: true});
new MutationObserver(() => {
    const isLoading = /Memeriksa|Meminta akses/.test(scanStatus.textContent);
    scanStatus.classList.toggle('loading-feedback', isLoading);
    scanStatus.classList.toggle('is-loading', isLoading);
}).observe(scanStatus, {childList: true, characterData: true, subtree: true});
</script>
@endpush
@endsection