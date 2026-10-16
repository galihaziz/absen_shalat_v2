(() => {
    const panel = document.querySelector('#faceScanner');
    if (!panel) return;

    const modelUrl = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@v0.22.2/weights';
    const video = document.querySelector('#faceCameraVideo');
    const overlay = document.querySelector('#faceCameraOverlay');
    const cameraToggle = document.querySelector('#faceCameraToggle');
    const cameraSourceToggle = document.querySelector('#faceCameraSourceToggle');
    const cameraStop = document.querySelector('#faceCameraStop');
    const placeholder = document.querySelector('#faceCameraPlaceholder');
    const status = document.querySelector('#faceScanStatus');
    const detectorMetrics = document.querySelector('#faceDetectorMetrics');
    const registrationFields = document.querySelector('#faceRegistrationFields');
    const sampleCount = document.querySelector('#faceSampleCount');
    const poseStep = document.querySelector('#facePoseStep');
    const poseFeedback = document.querySelector('#facePoseFeedback');
    const poseProgress = document.querySelector('#facePoseProgress');
    const registrationRetry = document.querySelector('#faceRegistrationRetry');
    const result = document.querySelector('#faceScanResult');
    const attendanceTypeHint = document.querySelector('#faceAttendanceTypeHint');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';

    if (!video || !overlay || !cameraToggle || !placeholder || !status) {
        return;
    }

    const cameraViewport = video.parentElement;
    const qualityCanvas = document.createElement('canvas');
    qualityCanvas.width = 48;
    qualityCanvas.height = 36;
    const qualityContext = qualityCanvas.getContext('2d', { willReadFrequently: true });
    const memory = navigator.deviceMemory || 4;
    const cores = navigator.hardwareConcurrency || 4;
    const lowPowerDevice = memory <= 4 || cores <= 4;
    const detectorInputSize = lowPowerDevice ? 128 : 160;
    const scanInterval = lowPowerDevice ? 180 : 120;
    const detectorOptions = new faceapi.TinyFaceDetectorOptions({
        inputSize: detectorInputSize,
        scoreThreshold: 0.45,
    });
    const autoStartCamera = panel.dataset.cameraAutoStart === 'true';
    const attendanceTypeButtons = Array.from(document.querySelectorAll('[data-attendance-type]'));
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
    let preferredFacing = 'user';
    let mode = panel.dataset.adminRegistration === 'true' ? 'registration' : 'attendance';
    let attendanceType = 'umum';
    let busy = false;
    let scanLatched = false;
    let cameraStarting = false;
    let stableDetections = 0;
    let stablePoseDetections = 0;
    let missingFaceDetections = 0;
    let frontPitch = null;
    let poseIndex = 0;
    let registrationComplete = false;
    let recognitionModelsPromise = null;
    let landmarkModelPromise = null;
    let detectionTimer = null;
    let detectionLoopRunning = false;
    let overlayAnimationFrame = null;
    let targetFaceBox = null;
    let displayedFaceBox = null;
    let previewIsHorizontallyFlipped = false;
    let lastOverlayFrameAt = 0;
    let metricSamplesSinceUpdate = 0;
    const inferenceSamples = [];

    const setStatus = (message, isError = false) => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('error-feedback', isError);
    };

    const canUseCamera = () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('Browser ini tidak mendukung akses kamera. Gunakan Chrome atau Edge versi terbaru.', true);
            return false;
        }
        if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
            setStatus('Halaman harus dibuka lewat HTTPS atau localhost agar kamera bisa dipakai.', true);
            return false;
        }
        return true;
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
        missingFaceDetections = 0;
        scanLatched = false;
        result.hidden = true;
        clearRegistration();
        setStatus(mode === 'registration' ? 'Ikuti arahan pose; wajah akan tersimpan otomatis.' : 'Arahkan kamera ke satu wajah untuk scan otomatis.');
    };

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
        missingFaceDetections = 0;
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

    const updateDetectorMetrics = (duration, landmarkDuration) => {
        const trackSettings = stream?.getVideoTracks()[0]?.getSettings() || {};
        inferenceSamples.push({ duration, landmarkDuration });
        if (inferenceSamples.length > 20) inferenceSamples.shift();
        metricSamplesSinceUpdate++;
        if (inferenceSamples.length < 5 || metricSamplesSinceUpdate < 5) return;
        metricSamplesSinceUpdate = 0;

        const durations = inferenceSamples.map((sample) => sample.duration).sort((left, right) => left - right);
        const average = durations.reduce((total, value) => total + value, 0) / durations.length;
        const p95 = durations[Math.min(durations.length - 1, Math.ceil(durations.length * 0.95) - 1)];
        const averageLandmarks = inferenceSamples.reduce((total, sample) => total + sample.landmarkDuration, 0) / inferenceSamples.length;
        const cameraResolution = trackSettings.width && trackSettings.height
            ? `${trackSettings.width}×${trackSettings.height}`
            : 'resolusi tidak tersedia';
        const cameraRate = trackSettings.frameRate ? ` · ${Math.round(trackSettings.frameRate)} fps` : '';
        detectorMetrics.hidden = false;
        detectorMetrics.textContent = `Deteksi rata-rata ${Math.round(average)} ms (P95 ${Math.round(p95)} ms; landmark ${Math.round(averageLandmarks)} ms) · kamera ${cameraResolution}${cameraRate}`;
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
        if (overlayAnimationFrame !== null) {
            window.cancelAnimationFrame(overlayAnimationFrame);
            overlayAnimationFrame = null;
        }
        targetFaceBox = null;
        displayedFaceBox = null;
        lastOverlayFrameAt = 0;
        const context = overlay.getContext('2d');
        context.setTransform(1, 0, 0, 1, 0, 0);
        context.clearRect(0, 0, overlay.width, overlay.height);
    };

    const drawDetection = (detection) => {
        if (!detection) {
            clearOverlay();
            return;
        }
        const { x, y, width, height } = detection.box || detection.detection.box;
        targetFaceBox = { x, y, width, height };
        if (!displayedFaceBox) {
            displayedFaceBox = { ...targetFaceBox };
        }
        startOverlayAnimation();
    };

    const renderFaceOverlay = () => {
        overlayAnimationFrame = null;
        if (!stream || document.hidden) return;

        const viewport = overlay.parentElement;
        const viewportWidth = viewport.clientWidth;
        const viewportHeight = viewport.clientHeight;
        const videoWidth = video.videoWidth;
        const videoHeight = video.videoHeight;
        if (!viewportWidth || !viewportHeight || !videoWidth || !videoHeight) {
            overlayAnimationFrame = window.requestAnimationFrame(renderFaceOverlay);
            return;
        }

        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        const canvasWidth = Math.round(viewportWidth * pixelRatio);
        const canvasHeight = Math.round(viewportHeight * pixelRatio);
        if (overlay.width !== canvasWidth || overlay.height !== canvasHeight) {
            overlay.width = canvasWidth;
            overlay.height = canvasHeight;
        }

        const context = overlay.getContext('2d');
        context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        context.clearRect(0, 0, viewportWidth, viewportHeight);

        if (targetFaceBox && displayedFaceBox) {
            const frameTime = performance.now();
            const frameDuration = lastOverlayFrameAt ? Math.min(frameTime - lastOverlayFrameAt, 50) : 16;
            const centerDeltaX = (targetFaceBox.x + targetFaceBox.width / 2) - (displayedFaceBox.x + displayedFaceBox.width / 2);
            const centerDeltaY = (targetFaceBox.y + targetFaceBox.height / 2) - (displayedFaceBox.y + displayedFaceBox.height / 2);
            const movement = Math.hypot(centerDeltaX, centerDeltaY);
            const fastMovementThreshold = Math.max(videoWidth, videoHeight) * 0.035;
            const smoothingTime = movement > fastMovementThreshold ? 24 : 55;
            const smoothing = 1 - Math.exp(-frameDuration / smoothingTime);
            displayedFaceBox.x += (targetFaceBox.x - displayedFaceBox.x) * smoothing;
            displayedFaceBox.y += (targetFaceBox.y - displayedFaceBox.y) * smoothing;
            displayedFaceBox.width += (targetFaceBox.width - displayedFaceBox.width) * smoothing;
            displayedFaceBox.height += (targetFaceBox.height - displayedFaceBox.height) * smoothing;
            lastOverlayFrameAt = frameTime;

            const scale = Math.max(viewportWidth / videoWidth, viewportHeight / videoHeight);
            const cropX = (videoWidth * scale - viewportWidth) / 2;
            const cropY = (videoHeight * scale - viewportHeight) / 2;
            context.strokeStyle = '#62e5c0';
            context.lineWidth = 3;
            context.lineJoin = 'round';
            context.strokeRect(
                displayedFaceBox.x * scale - cropX,
                displayedFaceBox.y * scale - cropY,
                displayedFaceBox.width * scale,
                displayedFaceBox.height * scale
            );
            const remainingMovement = Math.max(
                Math.abs(targetFaceBox.x - displayedFaceBox.x),
                Math.abs(targetFaceBox.y - displayedFaceBox.y),
                Math.abs(targetFaceBox.width - displayedFaceBox.width),
                Math.abs(targetFaceBox.height - displayedFaceBox.height)
            );
            if (remainingMovement > 0.25) {
                overlayAnimationFrame = window.requestAnimationFrame(renderFaceOverlay);
            }
        }
    };

    const imageQualityIssue = (detection) => {
        const width = video.videoWidth;
        const height = video.videoHeight;
        if (!width || !height || !qualityContext) return 'Kamera belum siap. Coba lagi sebentar.';

        const box = detection.box || detection.detection.box;
        const faceWidth = box.width / width;
        const faceHeight = box.height / height;
        const centerX = (box.x + box.width / 2) / width;
        const centerY = (box.y + box.height / 2) / height;
        if (faceWidth < 0.22 || faceHeight < 0.24) return 'Dekatkan wajah sedikit agar terlihat jelas di dalam kotak.';
        if (faceWidth > 0.78 || faceHeight > 0.86) return 'Mundurkan wajah sedikit agar seluruh wajah masuk kamera.';
        if (centerX < 0.28 || centerX > 0.72 || centerY < 0.18 || centerY > 0.82) {
            return 'Posisikan wajah di tengah kotak kamera.';
        }

        qualityContext.drawImage(video, 0, 0, qualityCanvas.width, qualityCanvas.height);
        const pixels = qualityContext.getImageData(0, 0, qualityCanvas.width, qualityCanvas.height).data;
        const luminance = new Float32Array(qualityCanvas.width * qualityCanvas.height);
        let brightness = 0;
        for (let pixel = 0, index = 0; pixel < pixels.length; pixel += 4, index++) {
            const value = 0.299 * pixels[pixel] + 0.587 * pixels[pixel + 1] + 0.114 * pixels[pixel + 2];
            luminance[index] = value;
            brightness += value;
        }
        brightness /= luminance.length;
        if (brightness < 42) return 'Pencahayaan terlalu redup. Nyalakan lampu dan hadapkan wajah ke sumber cahaya.';
        if (brightness > 218) return 'Wajah terlalu terang. Hindari cahaya langsung dari belakang kamera.';

        let laplacianMean = 0;
        let laplacianSquareMean = 0;
        let count = 0;
        for (let y = 1; y < qualityCanvas.height - 1; y++) {
            for (let x = 1; x < qualityCanvas.width - 1; x++) {
                const index = y * qualityCanvas.width + x;
                const laplacian = luminance[index - qualityCanvas.width]
                    + luminance[index + qualityCanvas.width]
                    + luminance[index - 1]
                    + luminance[index + 1]
                    - 4 * luminance[index];
                laplacianMean += laplacian;
                laplacianSquareMean += laplacian * laplacian;
                count++;
            }
        }
        const sharpness = laplacianSquareMean / count - (laplacianMean / count) ** 2;
        if (sharpness < 12) return 'Gambar wajah buram. Bersihkan lensa dan tahan ponsel tetap stabil.';
        return null;
    };

    const descriptorDistance = (left, right) => {
        let sum = 0;
        for (let index = 0; index < left.length; index++) {
            const difference = left[index] - right[index];
            sum += difference * difference;
        }
        return Math.sqrt(sum);
    };

    const detectFaces = async () => {
        if (!stream || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) return [];
        const needsLandmarks = mode === 'registration';
        if (needsLandmarks) await loadLandmarkModel();
        const startedAt = performance.now();
        const detectorTask = faceapi.detectAllFaces(video, detectorOptions);
        let detections;
        let landmarkDuration = 0;
        if (needsLandmarks) {
            const landmarkStartedAt = performance.now();
            detections = await detectorTask.withFaceLandmarks();
            landmarkDuration = performance.now() - landmarkStartedAt;
        } else {
            detections = await detectorTask;
        }
        updateDetectorMetrics(performance.now() - startedAt, landmarkDuration);
        return detections;
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
        const yaw = (noseX - eyesX) / eyeWidth;
        return { yaw: previewIsHorizontallyFlipped ? -yaw : yaw, pitch: (noseY - eyesY) / (mouthY - eyesY) };
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
        const qualityIssue = imageQualityIssue(descriptorDetection);
        if (qualityIssue) throw new Error(qualityIssue);
        return Array.from(descriptorDetection.descriptor);
    };

    const submitAttendance = async () => {
        if (busy || scanLatched) return;
        busy = true;
        scanLatched = true;
        result.hidden = true;
        setStatus('Wajah terlihat jelas. Tahan posisi, pemindaian akan dimulai sebentar lagi…');
        try {
            await new Promise((resolve) => window.setTimeout(resolve, 900));
            setStatus('Sedang membaca wajah. Mohon jangan bergerak…');
            attendanceSamples.splice(0, attendanceSamples.length, await captureDescriptor());
            setStatus('Sampel pertama terbaca. Memastikan ulang wajah…');
            await new Promise((resolve) => window.setTimeout(resolve, 350));
            attendanceSamples.push(await captureDescriptor());
            setStatus('Pemeriksaan selesai. Mengirim hasil scan…');
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
        poseFeedback.textContent = stablePoseDetections < 3
            ? 'Posisi sudah sesuai. Tahan dulu, jangan bergerak…'
            : 'Sedang memeriksa cahaya dan ketajaman wajah…';
        if (stablePoseDetections < 3) return;

        const qualityIssue = imageQualityIssue(detection);
        if (qualityIssue) {
            stablePoseDetections = 0;
            poseFeedback.textContent = qualityIssue;
            return;
        }

        stablePoseDetections = 0;
        poseFeedback.textContent = 'Pose pas. Sedang mengambil dua sampel wajah…';
        const poseDescriptors = [];
        try {
            poseDescriptors.push(await captureDescriptor());
            await new Promise((resolve) => window.setTimeout(resolve, 180));
            poseDescriptors.push(await captureDescriptor());
        } catch (error) {
            poseFeedback.textContent = error.message;
            return;
        }
        if (descriptorDistance(poseDescriptors[0], poseDescriptors[1]) > 0.35) {
            poseFeedback.textContent = 'Sampel berubah-ubah. Tahan ponsel stabil dan ulangi pose ini.';
            return;
        }
        poseFeedback.textContent = `${poses[poseIndex].name} berhasil direkam. Bersiap untuk arahan berikutnya…`;
        await new Promise((resolve) => window.setTimeout(resolve, 1000));
        registeredDescriptors.push(
            poseDescriptors[0].map((value, index) => (value + poseDescriptors[1][index]) / 2)
        );
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
        missingFaceDetections++;
        if (missingFaceDetections < 3) return;
        stableDetections = 0;
        stablePoseDetections = 0;
        if (mode === 'attendance') attendanceSamples.splice(0, attendanceSamples.length);
        if (scanLatched) {
            scanLatched = false;
            if (result.hidden) {
                setStatus('Wajah tidak terlihat. Arahkan kembali ke kamera untuk scan berikutnya.');
            }
        } else if (result.hidden && !busy) {
            setStatus(mode === 'registration'
                ? 'Wajah belum terlihat. Posisikan wajah di tengah kotak.'
                : 'Wajah belum terlihat. Arahkan wajah ke tengah kotak untuk scan.');
        } else if (!busy) {
            if (result.hidden) setStatus('Posisikan satu wajah di dalam kotak.');
        }
    };

    const scheduleDetection = (delay = scanInterval) => {
        if (!stream || document.hidden || detectionTimer !== null) return;
        detectionTimer = window.setTimeout(() => {
            detectionTimer = null;
            detectLoop();
        }, delay);
    };

    const startOverlayAnimation = () => {
        if (overlayAnimationFrame === null && stream && !document.hidden) {
            overlayAnimationFrame = window.requestAnimationFrame(renderFaceOverlay);
        }
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
                missingFaceDetections = 0;
                const box = detections[0];
                drawDetection(box);
                if (mode === 'registration') {
                    if (!busy && !registrationComplete) {
                        await processRegistrationPose(box);
                    }
                } else if (!busy && !scanLatched) {
                    stableDetections++;
                    if (stableDetections >= 2) {
                        const qualityIssue = imageQualityIssue(box);
                        if (qualityIssue) {
                            stableDetections = 0;
                            setStatus(qualityIssue);
                        } else {
                            await submitAttendance();
                        }
                    }
                    else setStatus('Wajah terlihat. Tetap diam sebentar agar hasil scan lebih tepat…');
                }
            }
        } catch (error) {
            stableDetections = 0;
            setStatus(error.message || 'Deteksi wajah terkendala. Pastikan kamera aktif dan coba lagi.', true);
        } finally {
            detectionLoopRunning = false;
            if (stream && !document.hidden) {
                const duration = performance.now() - startedAt;
                const recoveryDelay = Math.min(100, duration * 0.2);
                const adaptiveDelay = Math.max(scanInterval - duration, recoveryDelay);
                scheduleDetection(adaptiveDelay);
            }
        }
    };

    const updateCameraSourceLabel = () => {
        if (!cameraSourceToggle) return;
        const nextFacing = preferredFacing === 'user' ? 'belakang' : 'depan';
        cameraSourceToggle.textContent = `Pindah ke kamera ${nextFacing}`;
    };

    const getCameraConstraints = (facing) => ({
        audio: false,
        video: {
            facingMode: facing === 'environment' ? { ideal: 'environment' } : { ideal: 'user' },
            width: { ideal: 640, max: 1280 },
            height: { ideal: 480, max: 720 },
            frameRate: { ideal: lowPowerDevice ? 20 : 24, max: 30 },
            advanced: [{ focusMode: 'continuous' }],
        },
    });

    const stopCameraStream = () => {
        if (detectionTimer !== null) window.clearTimeout(detectionTimer);
        detectionTimer = null;
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
        }
        stream = null;
        video.srcObject = null;
        cameraViewport.classList.remove('is-front-camera');
        previewIsHorizontallyFlipped = false;
        detectorMetrics.hidden = true;
        inferenceSamples.splice(0, inferenceSamples.length);
        metricSamplesSinceUpdate = 0;
        clearOverlay();
        attendanceSamples.splice(0, attendanceSamples.length);
        stableDetections = 0;
    };

    const startCamera = async (requestedFacing = preferredFacing) => {
        if (!canUseCamera()) return;
        if (cameraStarting) return;
        if (stream && preferredFacing === requestedFacing) return;
        cameraStarting = true;
        cameraToggle.disabled = true;
        if (cameraSourceToggle) cameraSourceToggle.disabled = true;
        try {
            if (stream) {
                stopCameraStream();
            }
            preferredFacing = requestedFacing;
            updateCameraSourceLabel();
            placeholder.hidden = false;
            setStatus(`Meminta akses kamera ${preferredFacing === 'user' ? 'depan' : 'belakang'}…`);
            stream = await navigator.mediaDevices.getUserMedia(getCameraConstraints(requestedFacing));
            video.srcObject = stream;
            const actualFacing = stream.getVideoTracks()[0]?.getSettings().facingMode;
            previewIsHorizontallyFlipped = actualFacing
                ? actualFacing === 'user'
                : requestedFacing === 'user';
            cameraViewport.classList.toggle('is-front-camera', previewIsHorizontallyFlipped);
            await video.play();
            placeholder.hidden = true;
            cameraStop.disabled = false;
            setStatus('Kamera aktif. Memuat model deteksi wajah…');
            await loadModels();
            startOverlayAnimation();
            stableDetections = 0;
            setStatus('Model siap. Posisikan satu wajah di dalam kotak.');
            result.hidden = true;
            scanLatched = false;
            attendanceSamples.splice(0, attendanceSamples.length);
            scheduleDetection(0);
        } catch (error) {
            if (requestedFacing === 'environment' && error.name === 'NotFoundError') {
                setStatus('Kamera belakang tidak tersedia di perangkat ini. Beralih ke kamera depan…');
                return startCamera('user');
            }
            if (stream) stream.getTracks().forEach((track) => track.stop());
            stream = null;
            video.srcObject = null;
            placeholder.hidden = false;
            if (cameraStop) cameraStop.disabled = true;
            if (placeholder) {
                const placeholderTitle = placeholder.querySelector('strong');
                const placeholderMessage = placeholder.querySelector('span');
                if (placeholderTitle) placeholderTitle.textContent = 'Kamera belum aktif';
                if (placeholderMessage) placeholderMessage.textContent = error.message || 'Izinkan akses kamera dan pastikan halaman dibuka melalui HTTPS atau localhost.';
            }
            setStatus(error.message || 'Kamera tidak dapat dibuka. Periksa izin kamera dan HTTPS/localhost.', true);
        } finally {
            cameraStarting = false;
            cameraToggle.disabled = false;
            if (cameraSourceToggle) cameraSourceToggle.disabled = false;
        }
    };

    if (cameraToggle) {
        cameraToggle.addEventListener('click', () => startCamera(preferredFacing));
    }
    if (cameraSourceToggle) {
        cameraSourceToggle.addEventListener('click', () => {
            const nextFacing = preferredFacing === 'user' ? 'environment' : 'user';
            startCamera(nextFacing);
        });
    }

    if (cameraStop) {
        cameraStop.addEventListener('click', () => {
            stopCameraStream();
            cameraStop.disabled = true;
            placeholder.hidden = false;
            clearOverlay();
            result.hidden = true;
            scanLatched = false;
            setStatus('Kamera dimatikan.');
        });
    }

    document.querySelectorAll('[data-face-mode]').forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.faceMode));
    });

    if (registrationRetry) {
        registrationRetry.addEventListener('click', saveRegistration);
    }

    attendanceTypeButtons.forEach((button) => {
        button.addEventListener('click', () => setAttendanceType(button.dataset.attendanceType));
    });

    window.addEventListener('pagehide', () => {
        if (detectionTimer !== null) window.clearTimeout(detectionTimer);
        if (stream) stream.getTracks().forEach((track) => track.stop());
        clearOverlay();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (detectionTimer !== null) window.clearTimeout(detectionTimer);
            detectionTimer = null;
        } else {
            startOverlayAnimation();
            scheduleDetection(0);
        }
    });

    updateCameraSourceLabel();
    setMode(mode);
    setAttendanceType(attendanceType);
    if (autoStartCamera && canUseCamera()) {
        window.setTimeout(() => {
            if (cameraToggle && !cameraStarting && document.hasFocus()) {
                startCamera();
            }
        }, 250);
    } else {
        setStatus('Kamera belum aktif. Tekan “Mulai kamera” untuk memulai scan.');
    }
})();
