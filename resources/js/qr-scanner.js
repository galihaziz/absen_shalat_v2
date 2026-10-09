import { BrowserQRCodeReader } from '@zxing/browser';

const panel = document.getElementById('qrScanner');

if (panel) {
    const reader = new BrowserQRCodeReader(undefined, {
        delayBetweenScanAttempts: 100,
        delayBetweenScanSuccess: 150,
    });
    const video = document.getElementById('qrScanVideo');
    const startButton = document.getElementById('qrScanStart');
    const stopButton = document.getElementById('qrScanStop');
    const status = document.getElementById('qrScanStatus');
    const resultPanel = document.getElementById('qrScanResult');
    let controls = null;
    let processing = false;

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
    };

    const recordAttendance = async (rawValue) => {
        const induk = String(rawValue).trim();
        if (!/^\d{1,30}$/.test(induk)) {
            setStatus('QR tidak berisi nomor induk siswa yang valid.', true);
            return;
        }
        if (processing) return;

        processing = true;
        setStatus('Memeriksa data siswa...');
        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ induk }),
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Kehadiran gagal dicatat.');
            }

            document.getElementById('qrStudentName').textContent = payload.student.nama;
            document.getElementById('qrStudentInduk').textContent = `Induk ${payload.student.induk}`;
            document.getElementById('qrStudentClass').textContent = payload.student.kelas;
            document.getElementById('qrStudentScanDate').textContent = `Tanggal ${payload.tanggal} · Jam ${payload.waktu_scan}`;
            document.getElementById('qrAttendanceMessage').textContent = payload.message;
            resultPanel.hidden = false;
            setStatus(payload.already_recorded ? 'Sudah tercatat' : 'Berhasil dicatat');
        } catch (error) {
            setStatus(error.message || 'Kehadiran gagal dicatat. Coba lagi.', true);
        } finally {
            window.setTimeout(() => { processing = false; }, 1200);
        }
    };

    startButton.addEventListener('click', async () => {
        startButton.disabled = true;
        setStatus('Meminta akses kamera...');
        try {
            controls = await reader.decodeFromVideoDevice(undefined, video, (result) => {
                if (result) recordAttendance(result.getText());
            });
            stopButton.disabled = false;
            setStatus('Arahkan QR siswa ke dalam bingkai.');
        } catch (error) {
            startButton.disabled = false;
            const message = error.name === 'NotAllowedError'
                ? 'Akses kamera ditolak. Izinkan kamera pada browser lalu coba lagi.'
                : error.name === 'NotFoundError'
                    ? 'Kamera tidak ditemukan pada perangkat ini.'
                    : error.message || 'Kamera tidak dapat dibuka. Coba lagi.';
            setStatus(message, true);
        }
    });

    stopButton.addEventListener('click', () => {
        controls?.stop();
        controls = null;
        video.srcObject?.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
        startButton.disabled = false;
        stopButton.disabled = true;
        setStatus('Pemindaian dihentikan.');
    });
}
