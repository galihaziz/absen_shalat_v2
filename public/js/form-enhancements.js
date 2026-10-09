(() => {
    document.querySelectorAll('input[type="file"]').forEach((input) => {
        const feedback = document.createElement('span');
        feedback.className = 'file-selection-feedback';
        feedback.setAttribute('role', 'status');
        feedback.setAttribute('aria-live', 'polite');
        feedback.textContent = 'Belum ada file dipilih.';
        input.insertAdjacentElement('afterend', feedback);

        input.addEventListener('change', () => {
            const files = Array.from(input.files ?? []);
            if (!files.length) {
                feedback.textContent = 'Belum ada file dipilih.';
                return;
            }

            feedback.textContent = files.map((file) => {
                const size = file.size < 1024 * 1024
                    ? `${Math.max(1, Math.round(file.size / 1024))} KB`
                    : `${(file.size / (1024 * 1024)).toFixed(1)} MB`;
                return `${file.name} (${size})`;
            }).join(', ');
        });
    });
})();
