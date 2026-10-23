(() => {
    const form = document.querySelector('[data-import-upload-form]');
    if (!(form instanceof HTMLFormElement) || typeof XMLHttpRequest === 'undefined') return;

    const submitButton = form.querySelector('[data-import-upload-submit]');
    const progressPanel = form.querySelector('[data-import-upload-progress]');
    const title = form.querySelector('[data-import-upload-title]');
    const percentLabel = form.querySelector('[data-import-upload-percent]');
    const meter = form.querySelector('[data-import-upload-meter]');
    const detail = form.querySelector('[data-import-upload-detail]');
    const serverProcessing = form.querySelector('[data-import-server-processing]');
    const error = form.querySelector('[data-import-upload-error]');
    const progressTokenInput = form.querySelector('input[name="progress_token"]');
    const progressUrl = form.dataset.progressUrl?.replace(
        '__PROGRESS_TOKEN__',
        encodeURIComponent(progressTokenInput?.value || '')
    );
    let submitting = false;
    let progressTimer = null;
    let pollInFlight = false;

    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        const fileInput = form.querySelector('input[type="file"][name="excel"]');
        const file = fileInput?.files?.[0];
        if (!file) return;

        event.preventDefault();
        submitting = true;
        submitButton.disabled = true;
        progressPanel.hidden = false;
        progressPanel.setAttribute('aria-busy', 'true');
        error.hidden = true;
        serverProcessing.hidden = true;
        title.textContent = 'Mengirim file workbook';
        percentLabel.textContent = '0%';
        meter.value = 0;
        detail.textContent = `Menyiapkan ${file.name} (${formatBytes(file.size)}).`;

        const request = new XMLHttpRequest();
        request.open('POST', form.action);
        request.setRequestHeader('Accept', 'text/html');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) request.setRequestHeader('X-CSRF-TOKEN', csrfToken);

        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                detail.textContent = 'Sedang mengirim file workbook ke server…';
                return;
            }

            const percent = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100));
            const overallPercent = Math.round(percent * 0.2);
            meter.value = overallPercent;
            percentLabel.textContent = `${overallPercent}%`;
            detail.textContent = `Mengirim file: ${percent}% (${formatBytes(progressEvent.loaded)} dari ${formatBytes(progressEvent.total)}). Progres keseluruhan ${overallPercent}%.`;
        });

        request.upload.addEventListener('load', () => {
            meter.value = 20;
            percentLabel.textContent = '20%';
            title.textContent = 'Workbook diterima, sedang diproses';
            detail.textContent = 'Unggahan selesai. Meminta status proses pembacaan workbook dari server.';
            serverProcessing.hidden = false;
            pollServerProgress();
        });

        request.addEventListener('load', () => {
            if (request.status >= 200 && request.status < 300 && request.responseText) {
                clearTimeout(progressTimer);
                document.open();
                document.write(request.responseText);
                document.close();
                return;
            }

            showError(request.status === 413
                ? 'Upload ditolak karena ukuran file melebihi batas server. Coba file lebih kecil atau periksa konfigurasi upload hosting.'
                : `Import gagal diproses oleh server (HTTP ${request.status || 'tidak diketahui'}). Periksa file lalu coba lagi.`);
        });

        request.addEventListener('error', () => {
            showError('Koneksi terputus saat mengirim atau memproses workbook. Periksa koneksi internet lalu coba lagi.');
        });
        request.addEventListener('abort', () => {
            showError('Proses upload dibatalkan sebelum pratinjau selesai dibuat.');
        });

        request.send(new FormData(form));
    });

    function showError(message) {
        clearTimeout(progressTimer);
        submitting = false;
        submitButton.disabled = false;
        progressPanel.setAttribute('aria-busy', 'false');
        title.textContent = 'Proses belum selesai';
        detail.textContent = 'File belum berhasil menjadi pratinjau.';
        serverProcessing.hidden = true;
        error.textContent = message;
        error.hidden = false;
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KiB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MiB`;
    }

    async function pollServerProgress() {
        if (!submitting || !progressUrl || pollInFlight) return;
        pollInFlight = true;

        try {
            const response = await fetch(progressUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`Status proses tidak tersedia (HTTP ${response.status}).`);

            const state = await response.json();
            if (state && typeof state.percent === 'number') {
                meter.value = Math.max(20, Math.min(100, 20 + Math.round(state.percent * 0.8)));
                percentLabel.textContent = `${meter.value}%`;
                title.textContent = state.stage || 'Memproses workbook';
                detail.textContent = state.detail || 'Server sedang memproses workbook.';
                serverProcessing.hidden = Boolean(state.complete);
            }
        } catch {
            detail.textContent = 'Server masih memproses workbook. Status akan diperiksa kembali sebentar lagi.';
        } finally {
            pollInFlight = false;
            if (submitting) progressTimer = window.setTimeout(pollServerProgress, 900);
        }
    }
})();
