(() => {
    const panel = document.querySelector('#faceScanner');
    if (!panel) return;

    const modelUrl = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@v0.22.2/weights';
    const video = document.querySelector('#faceCameraVideo');
    const memory = navigator.deviceMemory || 4;
    const cores = navigator.hardwareConcurrency || 4;
    const lowPowerDevice = memory <= 4 || cores <= 4;
    const detectorInputSize = lowPowerDevice ? 128 : 160;
    const scanInterval = lowPowerDevice ? 280 : 180;
    const detectorOptions = new faceapi.TinyFaceDetectorOptions({
        inputSize: detectorInputSize,
        scoreThreshold: 0.45,
    });
    const overlay = document.querySelector('#faceCameraOverlay');
    const cameraToggle = document.querySelector('#faceCameraToggle');
    const cameraStop = document.querySelector('#faceCameraStop');
    const placeholder = document.querySelector('#faceCameraPlaceholder');
    const status = document.querySelector('#faceScanStatus');
    const registrationFields = document.querySelector('#faceRegistrationFields');
    const sampleCount = document.querySelector('#faceSampleCount');
    const poseStep = document.querySelector('#facePoseStep');
    const poseFeedback = document.querySelector('#facePoseFeedback');
    const poseProgress = document.querySelector('#facePoseProgress');
    const registrationRetry = document.querySelector('#faceRegistrationRetry');
    const result = document.querySelector('#faceScanResult');
    const attendanceTypeHint = document.querySelector('#faceAttendanceTypeHint');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const attendanceSamples = [];
    const registeredDescriptors = [];
    const poses = [
        { name: 'Lihat lurus ke kamera', matches: (pose) => Math.abs(pose.yaw) < 0.07 && pose.pitch > 0.39 && pose.pitch < 0.62 },
        { name: 'Putar kepala ke kiri sedikit', matches: (pose) => pose.yaw < -0.09 },
        { name: 'Putar kepala ke kanan sedikit', matches: (pose) => pose.yaw > 0.09 },
        { name: 'Arahkan pandangan ke atas', matches: (pose) => pose.pitch < 0.40 },
        { name: 'Arahkan pandangan ke bawah', matches: (pose) => pose.pitch > 0.61 },
    ];
    let stream = null;
    let modelsPromise = null;
    let mode = panel.dataset.adminRegistration === 'true' ? 'registration' : 'attendance';
    let attendanceType = 'umum';
    let busy = false;
    let scanLatched = false;
    let cameraStarting = false;
    let stableDetections = 0;
    let stablePoseDetections = 0;
    let frontPitch = null;
    let poseIndex = 0;
    let registrationComplete = false;
    let recognitionModelsPromise = null;
    let landmarkModelPromise = null;
    let detectionTimer = null;
    let detectionLoopRunning = false;

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('error-feedback', isError);
    };

    const clearRegistration = () => {
        registeredDescriptors.splice(0, registeredDescriptors.length);
        poseIndex = 0;
        stablePoseDetections = 0;
        frontPitch = null;
        registrationComplete = false;
        registrationRetry.hidden = true;
        sampleCount.textContent = '0 dari 5 pose';
        poseProgress.style.width = '0%';
        poseStep.textContent = poses[0].name;
        poseFeedback.textContent = 'Ikuti arahan posisi wajah. Posisi kiri dan kanan mengacu pada layar.';
    };

    const setMode = (nextMode) => {
        mode = nextMode;
        registrationFields.hidden = mode !== 'registration';
        document.querySelectorAll('[data-face-mode]').forEach((button) => {
            const selected = button.dataset.faceMode === mode;
            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('primary', selected);
        });
        attendanceTypeButtons.forEach((button) => {
            button.disabled = mode !== 'attendance';
        });
        attendanceSamples.splice(0, attendanceSamples.length);
        stableDetections = 0;
        scanLatched = false;
        result.hidden = true;
        clearRegistration();
        setStatus(mode === 'registration' ? 'Ikuti arahan pose; wajah akan tersimpan otomatis.' : 'Arahkan kamera ke satu wajah untuk scan otomatis.');
    };

    const attendanceTypeButtons = Array.from(document.querySelectorAll('[data-attendance-type]'));
    const setAttendanceType = (nextType) => {
        attendanceType = nextType;
        attendanceTypeButtons.forEach((button) => {
            const selected = button.dataset.attendanceType === attendanceType;
            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('primary', selected);
        });
        if (attendanceTypeHint) {
            attendanceTypeHint.textContent = attendanceType === 'haid'
                ? 'Mode Haid: siswi yang terverifikasi dicatat dengan keterangan M.'
                : 'Mode Umum: siswa yang terverifikasi dicatat hadir.';
        }
        attendanceSamples.splice(0, attendanceSamples.length);
        stableDetections = 0;
        scanLatched = false;
        result.hidden = true;
    };

    const loadModels = () => {
        if (!window.faceapi) return Promise.reject(new Error('Library wajah tidak termuat. Periksa koneksi internet lalu muat ulang.'));
        if (!modelsPromise) {
            modelsPromise = faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
        }
        return modelsPromise;
    };

    const loadLandmarkModel = () => {
        if (!landmarkModelPromise) {
            landmarkModelPromise = faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl).catch((error) => {
                landmarkModelPromise = null;
                throw error;
            });
        }
        return landmarkModelPromise;
    };

    const loadRecognitionModels = () => {
        if (!recognitionModelsPromise) {
            recognitionModelsPromise = Promise.all([
                loadLandmarkModel(),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl),
            ]).catch((error) => {
                recognitionModelsPromise = null;
                throw error;
            });
        }
        return recognitionModelsPromise;
    };

    const clearOverlay = () => {
        const context = overlay.getContext('2d');
        context.clearRect(0, 0, overlay.width, overlay.height);
    };

    const drawDetection = (detection) => {
        const width = video.videoWidth;
        const height = video.videoHeight;
        if (!width || !height) return;
        if (overlay.width !== width || overlay.height !== height) {
            overlay.width = width;
            overlay.height = height;
        }
        const context = overlay.getContext('2d');
        context.clearRect(0, 0, width, height);
        if (!detection) return;
        const { x, y, width: boxWidth, height: boxHeight } = detection.box || detection.detection.box;
        context.strokeStyle = '#62e5c0';
        context.lineWidth = Math.max(3, width / 240);
        context.shadowColor = 'rgba(59, 225, 181, .65)';
        context.shadowBlur = 12;
        context.strokeRect(x, y, boxWidth, boxHeight);
        context.shadowBlur = 0;
    };

    const detectFaces = async () => {
        if (!stream || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) return [];
        const needsLandmarks = mode === 'registration' || stableDetections > 0;
        if (needsLandmarks) await loadLandmarkModel();
        const detections = faceapi.detectAllFaces(video, detectorOptions);
        return needsLandmarks ? detections.withFaceLandmarks() : detections;
    };

    const headPose = (landmarks) => {
        const points = landmarks.positions;
        const averageX = (indices) => indices.reduce((total, index) => total + points[index].x, 0) / indices.length;
        const averageY = (indices) => indices.reduce((total, index) => total + points[index].y, 0) / indices.length;
        const eyesX = (averageX([36, 37, 38, 39, 40, 41]) + averageX([42, 43, 44, 45, 46, 47])) / 2;
        const eyesY = (averageY([36, 37, 38, 39, 40, 41]) + averageY([42, 43, 44, 45, 46, 47])) / 2;
        const eyeWidth = averageX([42, 43, 44, 45, 46, 47]) - averageX([36, 37, 38, 39, 40, 41]);
        const noseX = points[30].x;
        const noseY = points[30].y;
        const mouthY = (points[51].y + points[57].y) / 2;
        return { yaw: (noseX - eyesX) / eyeWidth, pitch: (noseY - eyesY) / (mouthY - eyesY) };
    };

    const showResult = (message, isError = false) => {
        result.hidden = false;
        result.textContent = message;
        result.classList.toggle('is-error', isError);
        setStatus(message, isError);
    };

    const postJson = async (endpoint, payload) => {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(payload),
        });
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error('Server tidak mengembalikan respons JSON yang valid.');
        }
        if (!response.ok) {
            const error = new Error(data.message || 'Permintaan gagal diproses.');
            error.status = response.status;
            throw error;
        }
        return data;
    };

    const captureDescriptor = async () => {
        await loadRecognitionModels();
        const descriptorDetection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.5 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        if (!descriptorDetection) throw new Error('Wajah berubah atau tidak terbaca. Tahan posisi dan coba lagi.');
        return Array.from(descriptorDetection.descriptor);
    };

    const submitAttendance = async () => {
        if (busy || scanLatched) return;
        busy = true;
        scanLatched = true;
        setStatus('Wajah stabil. Memverifikasi dan mencatat presensi…');
        try {
            attendanceSamples.splice(0, attendanceSamples.length, await captureDescriptor());
            const data = await postJson(panel.dataset.attendanceEndpoint, {
                descriptors: attendanceSamples,
                ...(attendanceType === 'haid' ? { jenis: 'haid' } : {}),
            });
            showResult(`${data.message} ${data.student.nama} · ${data.student.kelas} · ${data.waktu_scan}`);
        } catch (error) {
            showResult(error.message, true);
        } finally {
            busy = false;
            attendanceSamples.splice(0, attendanceSamples.length);
        }
    };

    const saveRegistration = async () => {
        const endpoint = panel.dataset.registrationEndpoint;
        const induk = panel.dataset.studentInduk.trim();
        if (!endpoint || !induk || registeredDescriptors.length !== poses.length) {
            showResult('Pastikan lima pose wajah selesai dipindai sebelum menyimpan.', true);
            return;
        }
        busy = true;
        registrationRetry.disabled = true;
        setStatus('Semua pose terbaca. Menyimpan data wajah…');
        try {
            const data = await postJson(endpoint, { induk, descriptors: registeredDescriptors });
            registrationComplete = true;
            registrationRetry.hidden = true;
            poseStep.textContent = 'Pendaftaran wajah selesai';
            poseFeedback.textContent = 'Lima variasi pose berhasil disimpan.';
            showResult(`${data.message} Kode unik wajah: ${data.kode_wajah}`);
        } catch (error) {
            if (error.status === 409) {
                clearRegistration();
            } else {
                registrationRetry.hidden = false;
            }
            showResult(error.message, true);
        } finally {
            busy = false;
            registrationRetry.disabled = false;
        }
    };

    const processRegistrationPose = async (detection) => {
        if (registrationComplete) return;
        if (poseIndex >= poses.length) return;
        const pose = headPose(detection.landmarks);
        if (poseIndex > 0 && frontPitch !== null) {
            pose.pitch -= frontPitch - 0.5;
        }
        if (!poses[poseIndex].matches(pose)) {
            stablePoseDetections = 0;
            poseFeedback.textContent = poseIndex === 0
                ? 'Pastikan wajah menghadap lurus dan kedua mata terlihat.'
                : `Pose belum sesuai. ${poses[poseIndex].name}.`;
            return;
        }
        stablePoseDetections++;
        poseFeedback.textContent = stablePoseDetections < 2 ? 'Bagus, tahan posisi sebentar…' : 'Pose stabil, mengambil sampel…';
        if (stablePoseDetections < 2) return;

        stablePoseDetections = 0;
        registeredDescriptors.push(await captureDescriptor());
        if (poseIndex === 0) frontPitch = pose.pitch;
        poseIndex++;
        sampleCount.textContent = `${poseIndex} dari ${poses.length} pose`;
        poseProgress.style.width = `${(poseIndex / poses.length) * 100}%`;
        if (poseIndex === poses.length) {
            await saveRegistration();
            return;
        }
        poseStep.textContent = poses[poseIndex].name;
        poseFeedback.textContent = 'Pose tersimpan. Lanjutkan ke arahan berikutnya.';
    };

    const handleLostFace = () => {
        stableDetections = 0;
        stablePoseDetections = 0;
        if (mode === 'attendance') attendanceSamples.splice(0, attendanceSamples.length);
        if (scanLatched) {
            scanLatched = false;
            result.hidden = true;
            result.classList.remove('is-error');
            setStatus('Wajah keluar dari kamera. Arahkan kembali untuk scan berikutnya.');
        } else if (!result.hidden) {
            result.hidden = true;
            setStatus(mode === 'registration' ? 'Posisikan wajah di dalam kotak.' : 'Arahkan kamera ke satu wajah untuk scan otomatis.');
        } else if (!busy) {
            setStatus('Posisikan satu wajah di dalam kotak.');
        }
    };

    const scheduleDetection = (delay = scanInterval) => {
        if (!stream || document.hidden || detectionTimer !== null) return;
        detectionTimer = window.setTimeout(() => {
            detectionTimer = null;
            detectLoop();
        }, delay);
    };

    const detectLoop = async () => {
        if (!stream || document.hidden || detectionLoopRunning) return;
        detectionLoopRunning = true;
        const startedAt = performance.now();
        try {
            const detections = await detectFaces();
            if (detections.length !== 1) {
                drawDetection(null);
                handleLostFace();
            } else {
                const box = detections[0];
                drawDetection(box);
                if (mode === 'registration') {
                    if (!busy && !registrationComplete) {
                        await processRegistrationPose(box);
                    }
                } else if (!busy && !scanLatched) {
                    stableDetections++;
                    if (stableDetections >= 2) await submitAttendance();
                    else setStatus('Wajah terdeteksi. Tahan sebentar untuk scan otomatis…');
                }
            }
        } catch (error) {
            stableDetections = 0;
            setStatus(error.message || 'Deteksi wajah terkendala. Pastikan kamera aktif dan coba lagi.', true);
        } finally {
            detectionLoopRunning = false;
            if (stream && !document.hidden) {
                const duration = performance.now() - startedAt;
                const adaptiveDelay = duration > 250
                    ? Math.min(scanInterval + 180, 500)
                    : duration > 140
                        ? Math.min(scanInterval + 80, 360)
                        : scanInterval;
                scheduleDetection(adaptiveDelay);
            }
        }
    };

    const startCamera = async () => {
        if (stream || cameraStarting) return;
        cameraStarting = true;
        cameraToggle.disabled = true;
        try {
            placeholder.hidden = false;
            setStatus('Meminta akses kamera… Izinkan akses pada prompt browser.');
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: 'user' },
                    width: { ideal: 480, max: 640 },
                    height: { ideal: 360, max: 480 },
                    frameRate: { ideal: 15, max: 24 },
                },
            });
            video.srcObject = stream;
            await video.play();
            placeholder.hidden = true;
            cameraStop.disabled = false;
            setStatus('Kamera aktif. Memuat model deteksi wajah…');
            await loadModels();
            stableDetections = 0;
            setStatus('Model siap. Posisikan satu wajah di dalam kotak.');
            result.hidden = true;
            scanLatched = false;
            attendanceSamples.splice(0, attendanceSamples.length);
            scheduleDetection(0);
        } catch (error) {
            if (stream) stream.getTracks().forEach((track) => track.stop());
            stream = null;
            video.srcObject = null;
            placeholder.hidden = false;
            cameraStop.disabled = true;
            placeholder.querySelector('strong').textContent = 'Kamera belum aktif';
            placeholder.querySelector('span').textContent = error.message || 'Izinkan akses kamera dan pastikan halaman dibuka melalui HTTPS atau localhost.';
            setStatus(error.message || 'Kamera tidak dapat dibuka. Periksa izin kamera dan HTTPS/localhost.', true);
        } finally {
            cameraStarting = false;
            cameraToggle.disabled = false;
        }
    };

    cameraToggle.addEventListener('click', startCamera);

    cameraStop.addEventListener('click', () => {
        if (detectionTimer !== null) window.clearTimeout(detectionTimer);
        detectionTimer = null;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        attendanceSamples.splice(0, attendanceSamples.length);
        stableDetections = 0;
        cameraStop.disabled = true;
        placeholder.hidden = false;
        clearOverlay();
        result.hidden = true;
        scanLatched = false;
        setStatus('Kamera dimatikan.');
    });

    document.querySelectorAll('[data-face-mode]').forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.faceMode));
    });

    registrationRetry.addEventListener('click', saveRegistration);

    attendanceTypeButtons.forEach((button) => {
        button.addEventListener('click', () => setAttendanceType(button.dataset.attendanceType));
    });

    window.addEventListener('pagehide', () => {
        if (detectionTimer !== null) window.clearTimeout(detectionTimer);
        if (stream) stream.getTracks().forEach((track) => track.stop());
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (detectionTimer !== null) window.clearTimeout(detectionTimer);
            detectionTimer = null;
        } else {
            scheduleDetection(0);
        }
    });

    setMode(mode);
    setAttendanceType(attendanceType);
    startCamera();
})();
