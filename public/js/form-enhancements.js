(() => {
    const registrationNotice = document.querySelector('[data-registration-notice]');
    if (registrationNotice) {
        const closeButton = registrationNotice.querySelector('[data-registration-notice-close]');
        const dialog = registrationNotice.querySelector('[role="alertdialog"]');
        const closeNotice = () => {
            registrationNotice.hidden = true;
        };

        registrationNotice.hidden = false;
        closeButton?.addEventListener('click', closeNotice);
        registrationNotice.addEventListener('click', (event) => {
            if (event.target === registrationNotice) closeNotice();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !registrationNotice.hidden) closeNotice();
        });
        dialog?.focus();
    }

    document.querySelectorAll('input[type="password"]').forEach((input, index) => {
        const wrapper = document.createElement('span');
        wrapper.className = 'password-input-wrap';
        wrapper.style.cssText = 'position:relative;display:flex;align-items:center;width:100%;';

        const controlId = input.id || `password-visibility-${index + 1}`;
        input.id = controlId;
        input.style.paddingRight = '48px';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.append(input);

        const toggle = document.createElement('button');
        toggle.className = 'password-visibility-toggle';
        toggle.style.cssText = 'position:absolute;top:50%;right:4px;display:grid;place-items:center;width:38px;height:38px;padding:0;transform:translateY(-50%);border:0;border-radius:4px;background:transparent;color:#617276;cursor:pointer;';
        toggle.type = 'button';
        toggle.setAttribute('aria-label', 'Tampilkan password');
        toggle.setAttribute('aria-controls', controlId);
        toggle.setAttribute('aria-pressed', 'false');
        toggle.title = 'Tampilkan password';
        toggle.innerHTML = `
            <svg class="password-visibility-icon password-visibility-icon-show" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="password-visibility-icon password-visibility-icon-hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                <path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.8M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7c1 0 1.9-.2 2.8-.5"></path>
            </svg>
        `;
        wrapper.append(toggle);

        toggle.addEventListener('click', () => {
            const isVisible = input.type === 'password';
            input.type = isVisible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(isVisible));
            toggle.setAttribute('aria-label', isVisible ? 'Sembunyikan password' : 'Tampilkan password');
            toggle.title = isVisible ? 'Sembunyikan password' : 'Tampilkan password';
        });
    });

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
