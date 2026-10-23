(() => {
    const form = document.querySelector('[data-student-photo-bulk]');
    if (!(form instanceof HTMLFormElement)) return;

    const scopeSelect = form.querySelector('[data-photo-bulk-scope]');
    const scopeTargets = form.querySelectorAll('[data-photo-bulk-target]');
    const fileInput = form.querySelector('[data-student-photo-files]');
    const submitButton = form.querySelector('[data-student-photo-submit]');
    const status = form.querySelector('[data-student-photo-bulk-status]');
    const fileSummary = form.querySelector('[data-student-photo-file-summary]');
    const fileList = form.querySelector('[data-student-photo-file-list]');
    const progressWrap = form.querySelector('[data-student-photo-progress-wrap]');
    const progress = form.querySelector('[data-student-photo-progress]');
    const dropzone = form.querySelector('.student-photo-dropzone');
    const matchBy = form.querySelector('[data-photo-match-by]');
    const matchHelp = form.querySelector('[data-photo-match-help]');
    const maxFiles = 100;
    const maxBytes = 100 * 1024;

    function formatBytes(bytes) {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KiB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MiB`;
    }

    function updateFileSelection(clearStatus = true) {
        const files = Array.from(fileInput.files || []);
        const totalBytes = files.reduce((total, file) => total + file.size, 0);
        if (fileSummary) {
            fileSummary.textContent = files.length
                ? `${files.length} foto dipilih · ${formatBytes(totalBytes)}`
                : 'Belum ada foto dipilih.';
            fileSummary.classList.toggle('is-warning', files.length > maxFiles);
        }
        if (fileList) {
            fileList.replaceChildren();
            files.slice(0, maxFiles).forEach((file) => {
                const item = document.createElement('li');
                const name = document.createElement('span');
                name.textContent = file.name;
                const size = document.createElement('small');
                size.textContent = formatBytes(file.size);
                item.append(name, size);
                fileList.append(item);
            });
            fileList.hidden = files.length === 0;
        }
        if (submitButton) {
            submitButton.disabled = files.length === 0 || files.length > maxFiles;
            submitButton.textContent = files.length > maxFiles
                ? `Kurangi pilihan menjadi maksimal ${maxFiles} foto`
                : files.length
                    ? `Unggah ${files.length} foto`
                    : 'Pilih foto untuk mulai';
        }
        if (status && files.length > maxFiles) {
            status.textContent = `Terlalu banyak foto dipilih. Batas unggah adalah ${maxFiles} foto per proses.`;
        } else if (clearStatus && status && !form.dataset.photoUploading) {
            status.textContent = '';
        }
    }

    function updateMatchHelp() {
        if (!matchHelp || !matchBy) return;
        matchHelp.textContent = matchBy.value === 'nama'
            ? 'Contoh nama file: Nama_Sis.jpg. Gunakan potongan nama yang jelas; jika ada nama mirip, gunakan pencocokan nomor induk.'
            : 'Contoh nama file: 123456.jpg. Pastikan nomor induk pada nama file sesuai dengan data siswa.';
    }

    function updateScopeTargets() {
        scopeTargets.forEach((target) => {
            const active = target.dataset.photoBulkTarget === scopeSelect.value;
            target.hidden = !active;
            target.querySelector('select').disabled = !active;
        });
    }

    function canvasBlob(canvas, quality) {
        return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    }

    async function decodeImage(file) {
        if (typeof createImageBitmap === 'function') return createImageBitmap(file);

        const objectUrl = URL.createObjectURL(file);
        const image = new Image();
        try {
            await new Promise((resolve, reject) => {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', reject, { once: true });
                image.src = objectUrl;
            });
            return image;
        } finally {
            URL.revokeObjectURL(objectUrl);
        }
    }

    async function compressPhoto(file) {
        if (file.size < maxBytes) return file;

        let bitmap;
        try {
            bitmap = await decodeImage(file);
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 1200 / bitmap.width, 1600 / bitmap.height);
            let width = Math.max(1, Math.round(bitmap.width * scale));
            let height = Math.max(1, Math.round(bitmap.height * scale));
            const context = canvas.getContext('2d');
            if (!context) return null;

            for (let attempt = 0; attempt < 12; attempt++) {
                canvas.width = width;
                canvas.height = height;
                context.fillStyle = '#fff';
                context.fillRect(0, 0, width, height);
                context.drawImage(bitmap, 0, 0, width, height);

                for (const quality of [0.82, 0.72, 0.62, 0.52, 0.42, 0.32]) {
                    const blob = await canvasBlob(canvas, quality);
                    if (blob && blob.size < maxBytes) {
                        const name = `${file.name.replace(/\.[^.]+$/, '') || 'foto'}.jpg`;
                        return new File([blob], name, { type: 'image/jpeg', lastModified: file.lastModified });
                    }
                }

                width = Math.max(1, Math.floor(width * 0.8));
                height = Math.max(1, Math.floor(height * 0.8));
            }

            return null;
        } catch {
            return null;
        } finally {
            bitmap?.close();
        }
    }

    function responseMessage(data) {
        if (data.message) return data.message;
        return Object.values(data.errors || {}).flat().join(' ') || 'Foto gagal diunggah.';
    }

    document.querySelectorAll('form[data-student-photo-compress]').forEach((photoForm) => {
        const photoInput = photoForm.querySelector('input[type="file"][name="foto"]');
        const feedback = photoForm.querySelector('[data-student-photo-upload-status]');
        if (!(photoInput instanceof HTMLInputElement)) return;

        photoForm.addEventListener('submit', async (event) => {
            if (photoForm.dataset.photoCompressed === 'true') {
                delete photoForm.dataset.photoCompressed;
                return;
            }

            const file = photoInput.files?.[0];
            if (!file || file.size < maxBytes) return;

            event.preventDefault();
            const button = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
            if (button) button.disabled = true;
            if (feedback) feedback.textContent = 'Mengompres foto sebelum dikirim…';

            try {
                const uploadFile = await compressPhoto(file);
                if (!uploadFile) {
                    if (feedback) feedback.textContent = `${file.name}: foto tidak dapat dikompres di browser. Pilih foto yang lebih kecil atau gunakan browser terbaru.`;
                    return;
                }
                if (typeof DataTransfer === 'undefined' || typeof photoForm.requestSubmit !== 'function') {
                    if (feedback) feedback.textContent = 'Browser tidak mendukung penggantian file hasil kompresi. Gunakan browser versi terbaru.';
                    return;
                }

                const files = new DataTransfer();
                files.items.add(uploadFile);
                photoInput.files = files.files;
                photoForm.dataset.photoCompressed = 'true';
                if (button) button.disabled = false;
                photoForm.requestSubmit(button || undefined);
            } catch {
                if (feedback) feedback.textContent = 'Kompresi foto gagal di browser. Foto tidak dikirim; coba file lain atau browser terbaru.';
            } finally {
                if (button) button.disabled = false;
            }
        });
    });

    updateScopeTargets();
    scopeSelect.addEventListener('change', updateScopeTargets);
    matchBy?.addEventListener('change', updateMatchHelp);
    updateMatchHelp();
    fileInput.addEventListener('change', () => {
        if (progressWrap) progressWrap.hidden = true;
        if (progress) progress.value = 0;
        updateFileSelection();
    });
    dropzone?.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    });
    dropzone?.addEventListener('dragleave', (event) => {
        if (!dropzone.contains(event.relatedTarget)) dropzone.classList.remove('is-dragging');
    });
    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        if (event.dataTransfer?.files?.length) {
            fileInput.files = event.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const files = Array.from(fileInput.files || []);
        if (files.length === 0) {
            status.textContent = 'Pilih minimal satu foto.';
            return;
        }
        if (files.length > maxFiles) {
            status.textContent = `Pilih maksimal ${maxFiles} foto dalam satu proses.`;
            return;
        }

        submitButton.disabled = true;
        form.dataset.photoUploading = 'true';
        fileInput.disabled = true;
        if (progressWrap) progressWrap.hidden = false;
        if (progress) {
            progress.max = files.length;
            progress.value = 0;
        }
        const errors = [];
        const uploadedStudentIds = new Set();
        let uploadedCount = 0;

        for (const [index, file] of files.entries()) {
            status.textContent = `Menyiapkan foto ${index + 1} dari ${files.length}…`;

            try {
                const uploadFile = await compressPhoto(file);
                if (!uploadFile) {
                    errors.push(`${file.name}: tidak dapat dikompres di bawah 100 KiB.`);
                    continue;
                }

                const body = new FormData(form);
                body.delete('fotos[]');
                body.append('fotos[]', uploadFile, uploadFile.name);

                const response = await fetch(form.action, {
                    method: 'POST',
                    body,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const responseBody = await response.text();
                let result = {};
                try {
                    result = JSON.parse(responseBody);
                } catch {
                    result.message = response.status === 413
                        ? 'Server menolak ukuran upload. Atur batas post_max_size hosting lebih besar atau coba lagi setelah menyesuaikan konfigurasi server.'
                        : `Server memberikan respons yang tidak dikenali (HTTP ${response.status}).`;
                }
                if (!response.ok || !result.success) {
                    errors.push(`${file.name}: ${responseMessage(result)}`);
                    continue;
                }

                if (uploadedStudentIds.has(result.student_id)) {
                    errors.push(`${file.name}: siswa ini sudah menerima foto dari file sebelumnya.`);
                    continue;
                }
                uploadedStudentIds.add(result.student_id);
                uploadedCount++;
            } catch {
                errors.push(`${file.name}: terjadi kendala saat mengunggah. Periksa koneksi lalu coba lagi.`);
            } finally {
                if (progress) progress.value = index + 1;
            }
        }

        status.textContent = `${uploadedCount} dari ${files.length} foto berhasil diunggah.`
            + (errors.length ? ` Gagal: ${errors.slice(0, 8).join(' ')}${errors.length > 8 ? ` Dan ${errors.length - 8} foto lainnya gagal.` : ''}` : '');
        delete form.dataset.photoUploading;
        fileInput.disabled = false;
        fileInput.value = '';
        updateFileSelection(false);
    });
})();
