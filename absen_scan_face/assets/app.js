(() => {
    const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@v0.22.2/weights';
    const MAX_SAMPLES = 3;

    const video = document.querySelector('#camera-video');
    const registrationVideo = document.querySelector('#registration-video');
    const videos = [video, registrationVideo];
    const elements = {
        cameraToggle: document.querySelector('#camera-toggle'),
        registrationCameraToggle: document.querySelector('#registration-camera-toggle'),
        cameraSelect: document.querySelector('#camera-select'),
        attendanceButton: document.querySelector('#attendance-button'),
        recognitionResult: document.querySelector('#recognition-result'),
        faceStatus: document.querySelector('#face-status-text'),
        registrationFaceText: document.querySelector('#registration-face-text'),
        registrationFaceDot: document.querySelector('#registration-face-dot'),
        sampleButton: document.querySelector('#sample-button'),
        registerButton: document.querySelector('#register-button'),
        sampleCount: document.querySelector('#sample-count'),
        sampleProgress: document.querySelector('#sample-progress-bar'),
        registrationForm: document.querySelector('#registration-form'),
        registrationMessage: document.querySelector('#registration-message'),
        historyKey: document.querySelector('#history-key'),
        historyBody: document.querySelector('#history-body'),
        historyMessage: document.querySelector('#history-message'),
        toast: document.querySelector('#toast'),
        placeholder: document.querySelector('#camera-placeholder'),
        registrationPlaceholder: document.querySelector('#registration-placeholder'),
        cameraLive: document.querySelector('#camera-live'),
        registrationLive: document.querySelector('#registration-live'),
        attendanceCount: document.querySelector('#attendance-count'),
    };

    let cameraStream = null;
    let detectionTimer = null;
    let detectionBusy = false;
    let modelsReady = false;
    let modelsPromise = null;
    let currentDescriptor = null;
    let samples = [];
    let toastTimer = null;
    let pageName = 'attendance';

    const dateOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
    const today = new Date();
    document.querySelector('#today-label').textContent = new Intl.DateTimeFormat('id-ID', dateOptions).format(today);
    document.querySelector('#today-card-date').textContent = new Intl.DateTimeFormat('id-ID', dateOptions).format(today);
    document.querySelector('#summary-date').textContent = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(today);
    document.querySelector('#year-label').textContent = String(today.getFullYear());

    function showToast(message, type = 'success') {
        elements.toast.textContent = message;
        elements.toast.className = `toast ${type}`;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => elements.toast.classList.add('hidden'), 4200);
    }

    function setMessage(element, message, type = 'error') {
        element.textContent = message;
        element.className = `inline-message ${type}`;
    }

    function clearMessage(element) {
        element.textContent = '';
        element.className = 'inline-message hidden';
    }

    function setCameraStatus(message, active = false) {
        elements.faceStatus.textContent = message;
        document.querySelector('#face-status').classList.toggle('is-detected', active);
        elements.registrationFaceText.textContent = message;
        elements.registrationFaceDot.classList.toggle('is-detected', active);
    }

    async function loadModels() {
        if (modelsReady) return;
        if (modelsPromise) return modelsPromise;
        if (!window.faceapi) {
            throw new Error('Library deteksi wajah tidak berhasil dimuat. Periksa koneksi internet lalu muat ulang halaman.');
        }
        modelsPromise = Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]).then(() => {
            modelsReady = true;
        }).catch((error) => {
            modelsPromise = null;
            throw error;
        });
        return modelsPromise;
    }

    async function refreshCameraList() {
        if (!navigator.mediaDevices?.enumerateDevices) return;
        const devices = await navigator.mediaDevices.enumerateDevices();
        const cameras = devices.filter((device) => device.kind === 'videoinput');
        const selected = elements.cameraSelect.value;
        elements.cameraSelect.replaceChildren(new Option('Kamera default', ''));
        cameras.forEach((camera, index) => {
            elements.cameraSelect.add(new Option(camera.label || `Kamera ${index + 1}`, camera.deviceId));
        });
        if (cameras.some((camera) => camera.deviceId === selected)) {
            elements.cameraSelect.value = selected;
        }
    }

    async function startCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Browser ini tidak mendukung akses kamera. Coba gunakan browser modern melalui localhost atau HTTPS.');
        }
        const selectedDeviceId = elements.cameraSelect.value;
        const constraints = {
            audio: false,
            video: selectedDeviceId
                ? { deviceId: { exact: selectedDeviceId } }
                : {
                    facingMode: { ideal: 'user' },
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                },
        };
        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (error) {
            const canRetryWithoutConstraints = !selectedDeviceId
                && ['NotReadableError', 'OverconstrainedError', 'AbortError'].includes(error?.name);
            if (!canRetryWithoutConstraints) throw error;
            stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
        }
        cameraStream = stream;
        videos.forEach((cameraVideo) => {
            cameraVideo.srcObject = stream;
            cameraVideo.classList.add('visible');
        });
        await Promise.all(videos.map((cameraVideo) => cameraVideo.play()));
        elements.placeholder.classList.add('hidden');
        elements.registrationPlaceholder.classList.add('hidden');
        elements.cameraLive.classList.add('visible');
        elements.registrationLive.classList.add('visible');
        elements.cameraToggle.innerHTML = '<span aria-hidden="true">◉</span> Matikan kamera';
        elements.registrationCameraToggle.innerHTML = '<span aria-hidden="true">◉</span> Matikan kamera';
        refreshCameraList().catch((error) => {
            console.warn('Daftar kamera tidak dapat diperbarui:', error);
        });
        setCameraStatus('Kamera aktif — memuat model wajah…');
        loadModels().then(() => {
            if (cameraStream) startDetection();
        }).catch((error) => {
            console.error('Model deteksi wajah tidak dapat dimuat:', error);
            showToast('Kamera aktif, tetapi model wajah gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.', 'error');
            setCameraStatus('Kamera aktif — model wajah gagal dimuat');
        });
    }

    function cameraErrorMessage(error) {
        switch (error?.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
            case 'SecurityError':
                return 'Izin kamera ditolak. Izinkan kamera untuk situs ini melalui ikon gembok/pengaturan situs di browser, lalu coba lagi.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'Kamera tidak ditemukan. Sambungkan kamera, tutup aplikasi lain yang memakai kamera, lalu muat ulang halaman.';
            case 'NotReadableError':
            case 'TrackStartError':
            case 'AbortError':
                return 'Kamera terdeteksi tetapi gagal dibuka (timeout starting video output). Tutup Zoom/Meet/aplikasi kamera lain, periksa izin kamera Windows, pilih kamera lain, lalu coba lagi.';
            case 'OverconstrainedError':
            case 'ConstraintNotSatisfiedError':
                return 'Pengaturan kamera yang dipilih tidak didukung. Pilih “Kamera default” atau pilih perangkat kamera lain.';
            default:
                return `Kamera tidak dapat dibuka${error?.message ? `: ${error.message}` : '. Periksa izin kamera dan coba pilih kamera lain.'}`;
        }
    }

    function clearOverlay(canvas) {
        const context = canvas.getContext('2d');
        context.clearRect(0, 0, canvas.width, canvas.height);
    }

    function stopCamera() {
        if (cameraStream) cameraStream.getTracks().forEach((track) => track.stop());
        cameraStream = null;
        videos.forEach((cameraVideo) => {
            cameraVideo.srcObject = null;
            cameraVideo.classList.remove('visible');
        });
        window.clearTimeout(detectionTimer);
        detectionTimer = null;
        currentDescriptor = null;
        elements.attendanceButton.disabled = true;
        elements.sampleButton.disabled = true;
        elements.placeholder.classList.remove('hidden');
        elements.registrationPlaceholder.classList.remove('hidden');
        elements.cameraLive.classList.remove('visible');
        elements.registrationLive.classList.remove('visible');
        elements.cameraToggle.innerHTML = '<span aria-hidden="true">◉</span> Aktifkan kamera';
        elements.registrationCameraToggle.innerHTML = '<span aria-hidden="true">◉</span> Aktifkan kamera';
        clearOverlay(document.querySelector('#camera-overlay'));
        clearOverlay(document.querySelector('#registration-overlay'));
        setCameraStatus('Menunggu kamera');
    }

    function drawDetection(canvas, result, sourceVideo) {
        const width = sourceVideo.videoWidth;
        const height = sourceVideo.videoHeight;
        if (!width || !height) return;
        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
        }
        const context = canvas.getContext('2d');
        context.clearRect(0, 0, width, height);
        if (!result) return;
        const box = result.detection.box;
        context.strokeStyle = '#62e5c0';
        context.lineWidth = Math.max(3, width / 320);
        context.shadowColor = 'rgba(59, 225, 181, .55)';
        context.shadowBlur = 14;
        context.strokeRect(box.x, box.y, box.width, box.height);
        context.shadowBlur = 0;
    }

    async function runDetection() {
        if (!cameraStream || detectionBusy || !modelsReady) return;
        detectionBusy = true;
        const activeVideo = pageName === 'registration' ? registrationVideo : video;
        const canvas = pageName === 'registration'
            ? document.querySelector('#registration-overlay')
            : document.querySelector('#camera-overlay');
        try {
            if (activeVideo.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                const results = await faceapi
                    .detectAllFaces(activeVideo, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
                    .withFaceLandmarks()
                    .withFaceDescriptors();
                const singleFace = results.length === 1 ? results[0] : null;
                currentDescriptor = singleFace ? Array.from(singleFace.descriptor) : null;
                drawDetection(canvas, singleFace, activeVideo);
                elements.attendanceButton.disabled = !singleFace;
                elements.sampleButton.disabled = !singleFace || samples.length >= MAX_SAMPLES;
                if (results.length > 1) {
                    setCameraStatus('Hanya satu wajah dalam bingkai');
                } else if (singleFace) {
                    setCameraStatus('Wajah terdeteksi', true);
                } else {
                    setCameraStatus('Posisikan wajah di dalam bingkai');
                }
            }
        } catch (error) {
            setCameraStatus('Deteksi wajah mengalami kendala');
        } finally {
            detectionBusy = false;
            detectionTimer = window.setTimeout(runDetection, 180);
        }
    }

    function startDetection() {
        if (detectionTimer) window.clearTimeout(detectionTimer);
        runDetection();
    }

    async function toggleCamera() {
        if (cameraStream) {
            stopCamera();
            return;
        }
        try {
            setCameraStatus('Meminta akses kamera…');
            await startCamera();
        } catch (error) {
            stopCamera();
            showToast(cameraErrorMessage(error), 'error');
        }
    }

    async function postApi(payload, adminKey = '') {
        const headers = { 'Content-Type': 'application/json' };
        if (adminKey) headers['X-Admin-Key'] = adminKey;
        const response = await fetch('api.php', {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.error || 'Permintaan gagal diproses.');
        return data;
    }

    function averageSamples() {
        return samples[0].map((_, index) => samples.reduce((sum, sample) => sum + sample[index], 0) / samples.length);
    }

    function renderSampleProgress() {
        elements.sampleCount.textContent = `${samples.length} dari ${MAX_SAMPLES}`;
        elements.sampleProgress.style.width = `${(samples.length / MAX_SAMPLES) * 100}%`;
        elements.registerButton.disabled = samples.length < MAX_SAMPLES;
        elements.sampleButton.disabled = !currentDescriptor || samples.length >= MAX_SAMPLES;
    }

    elements.cameraToggle.addEventListener('click', toggleCamera);
    elements.registrationCameraToggle.addEventListener('click', toggleCamera);
    elements.cameraSelect.addEventListener('change', async () => {
        if (!cameraStream) return;
        stopCamera();
        try {
            await startCamera();
        } catch (error) {
            stopCamera();
            showToast(cameraErrorMessage(error), 'error');
        }
    });

    elements.sampleButton.addEventListener('click', () => {
        if (!currentDescriptor || samples.length >= MAX_SAMPLES) return;
        samples.push(currentDescriptor.slice());
        renderSampleProgress();
        clearMessage(elements.registrationMessage);
        if (samples.length === MAX_SAMPLES) {
            setMessage(elements.registrationMessage, 'Tiga sampel siap. Periksa identitas lalu simpan registrasi.', 'success');
        }
    });

    elements.registrationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (samples.length < MAX_SAMPLES) {
            setMessage(elements.registrationMessage, 'Ambil tiga sampel wajah sebelum menyimpan.');
            return;
        }
        const form = new FormData(elements.registrationForm);
        const adminKey = String(form.get('admin_key') || '');
        elements.registerButton.disabled = true;
        try {
            const result = await postApi({
                action: 'register',
                employee_id: String(form.get('employee_id') || '').trim(),
                full_name: String(form.get('full_name') || '').trim(),
                descriptor: averageSamples(),
            }, adminKey);
            setMessage(elements.registrationMessage, result.message, 'success');
            elements.registrationForm.reset();
            samples = [];
            renderSampleProgress();
            showToast('Registrasi wajah berhasil disimpan.');
        } catch (error) {
            setMessage(elements.registrationMessage, error.message);
        } finally {
            elements.registerButton.disabled = samples.length < MAX_SAMPLES;
        }
    });

    elements.attendanceButton.addEventListener('click', async () => {
        if (!cameraStream || !modelsReady) {
            showToast('Pastikan satu wajah terdeteksi sebelum mencatat absensi.', 'error');
            return;
        }
        elements.attendanceButton.disabled = true;
        elements.recognitionResult.className = 'recognition-result visible pending';
        elements.recognitionResult.textContent = 'Mengambil tiga sampel wajah untuk verifikasi…';
        try {
            const descriptors = await captureVerificationSamples();
            elements.recognitionResult.textContent = 'Memeriksa kecocokan tiga sampel…';
            const result = await postApi({ action: 'attendance', descriptors });
            elements.recognitionResult.className = 'recognition-result visible success';
            elements.recognitionResult.textContent = `${result.employee.full_name} (${result.employee.employee_id}) — ${result.message} ${result.time}`;
            elements.attendanceCount.textContent = String(result.count);
            showToast(`Absensi ${result.employee.full_name} berhasil dicatat.`);
        } catch (error) {
            elements.recognitionResult.className = 'recognition-result visible error';
            elements.recognitionResult.textContent = error.message;
            showToast(error.message, 'error');
        } finally {
            elements.attendanceButton.disabled = !currentDescriptor;
        }
    });

    async function captureVerificationSamples() {
        const activeVideo = pageName === 'registration' ? registrationVideo : video;
        const descriptors = [];
        for (let sample = 0; sample < 3; sample++) {
            if (!cameraStream) throw new Error('Kamera dimatikan saat verifikasi. Aktifkan kembali lalu coba lagi.');
            if (sample > 0) await new Promise((resolve) => window.setTimeout(resolve, 350));
            const result = await faceapi
                .detectSingleFace(activeVideo, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();
            if (!result) {
                throw new Error('Wajah tidak terlihat jelas pada seluruh sampel. Tatap kamera dengan pencahayaan cukup lalu coba lagi.');
            }
            descriptors.push(Array.from(result.descriptor));
        }
        return descriptors;
    }

    function displayDate(value) {
        const date = new Date(`${value}T00:00:00+07:00`);
        return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
    }

    function renderHistory(records, todayCount) {
        elements.historyBody.replaceChildren();
        if (!records.length) {
            const row = elements.historyBody.insertRow();
            const cell = row.insertCell();
            cell.colSpan = 5;
            cell.className = 'empty-table';
            cell.textContent = 'Belum ada catatan absensi.';
            return;
        }
        records.forEach((record) => {
            const row = elements.historyBody.insertRow();
            [record.employee_id, record.full_name, displayDate(record.attendance_date), String(record.attendance_time).slice(0, 8)].forEach((value) => {
                row.insertCell().textContent = value;
            });
            const statusCell = row.insertCell();
            const status = document.createElement('span');
            status.className = 'attendance-pill';
            status.textContent = 'Hadir';
            statusCell.append(status);
        });
        elements.attendanceCount.textContent = String(todayCount);
    }

    document.querySelector('#load-history').addEventListener('click', async () => {
        const adminKey = elements.historyKey.value;
        if (!adminKey) {
            setMessage(elements.historyMessage, 'Masukkan kunci admin untuk membuka riwayat.');
            return;
        }
        clearMessage(elements.historyMessage);
        try {
            const result = await postApi({ action: 'history' }, adminKey);
            renderHistory(result.records, result.today_count);
        } catch (error) {
            setMessage(elements.historyMessage, error.message);
        }
    });

    document.querySelectorAll('.nav-item').forEach((button) => {
        button.addEventListener('click', () => {
            pageName = button.dataset.page;
            document.querySelectorAll('.nav-item').forEach((item) => item.classList.toggle('active', item === button));
            document.querySelectorAll('.page').forEach((page) => page.classList.toggle('active', page.id === `page-${pageName}`));
            document.querySelector('#breadcrumb-current').textContent = button.textContent.trim();
            if (cameraStream) startDetection();
        });
    });

    refreshCameraList().catch((error) => {
        console.warn('Daftar kamera belum dapat dibaca:', error);
    });

    window.addEventListener('pagehide', stopCamera);
})();
