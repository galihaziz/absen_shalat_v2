(() => {
    const list = document.querySelector('[data-student-list]');
    const tableWrap = list?.querySelector('[data-student-table-wrap]');
    const tableBody = list?.querySelector('[data-student-table-body]');
    const trigger = list?.querySelector('[data-student-load-trigger]');
    const loadButton = list?.querySelector('[data-student-load-button]');
    const loadStatus = list?.querySelector('[data-student-load-status]');
    const countLabel = list?.querySelector('[data-student-count]');
    const searchForm = list?.querySelector('[data-student-search]');
    const bulkAlumniForm = list?.querySelector('[data-bulk-alumni-form]');
    const alumniSelectionCount = list?.querySelector('[data-alumni-selection-count]');
    const alumniSelectAll = list?.querySelector('[data-alumni-select-all]');

    if (!list || !tableWrap || !tableBody || !trigger || !loadButton || !loadStatus || !countLabel) return;

    let nextPageUrl = list.dataset.nextPage || null;
    let isLoading = false;
    let loadedCount = Number(countLabel.dataset.count) || 0;
    const canObserve = 'IntersectionObserver' in window;

    const updateControls = () => {
        trigger.hidden = !nextPageUrl;
        loadButton.hidden = canObserve;
        loadButton.disabled = false;
        countLabel.textContent = nextPageUrl ? `${loadedCount} ditampilkan` : `${loadedCount} siswa`;
        countLabel.dataset.count = String(loadedCount);
    };

    const updateAlumniSelection = () => {
        if (!bulkAlumniForm) return;
        const checkboxes = Array.from(tableBody.querySelectorAll('[data-alumni-student]'));
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        alumniSelectionCount.textContent = `${selected} siswa dipilih`;
        bulkAlumniForm.querySelectorAll('[data-alumni-action]').forEach((button) => {
            button.disabled = selected === 0;
        });
        alumniSelectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
        alumniSelectAll.indeterminate = selected > 0 && selected < checkboxes.length;
    };

    tableBody.addEventListener('change', (event) => {
        if (event.target.matches('[data-alumni-student]')) updateAlumniSelection();
    });
    alumniSelectAll?.addEventListener('change', () => {
        tableBody.querySelectorAll('[data-alumni-student]').forEach((checkbox) => {
            checkbox.checked = alumniSelectAll.checked;
        });
        updateAlumniSelection();
    });
    bulkAlumniForm?.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        if (submitter?.value === 'delete' && !window.confirm('Hapus siswa alumni yang dipilih beserta seluruh riwayat absensinya?')) {
            event.preventDefault();
        }
    });

    const loadNextPage = async () => {
        if (isLoading || !nextPageUrl) return;

        isLoading = true;
        loadButton.disabled = true;
        loadStatus.textContent = 'Memuat siswa...';
        loadStatus.classList.add('is-loading');
        const skeletonRow = document.createElement('tr');
        skeletonRow.className = 'loading-skeleton-row';
        skeletonRow.setAttribute('aria-hidden', 'true');
        const skeletonCell = document.createElement('td');
        skeletonCell.colSpan = tableBody.closest('table').querySelectorAll('thead th').length;
        skeletonCell.innerHTML = '<span class="loading-shimmer"></span>';
        skeletonRow.append(skeletonCell);
        tableBody.append(skeletonRow);

        try {
            const response = await fetch(nextPageUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Data siswa gagal dimuat.');

            const result = await response.json();
            tableBody.insertAdjacentHTML('beforeend', result.html);
            loadedCount += Number(result.count) || 0;
            updateAlumniSelection();
            nextPageUrl = result.nextPageUrl;
            loadStatus.textContent = '';
            updateControls();
        } catch {
            loadStatus.textContent = 'Gagal memuat. Tekan tombol untuk mencoba lagi.';
            loadButton.hidden = false;
        } finally {
            skeletonRow.remove();
            loadStatus.classList.remove('is-loading');
            isLoading = false;
            loadButton.disabled = false;
        }
    };

    loadButton.addEventListener('click', loadNextPage);
    searchForm?.addEventListener('submit', () => {
        const button = searchForm.querySelector('button');
        if (button) {
            button.disabled = true;
            button.textContent = 'Mencari...';
        }
    });
    const studentCreatePanel = document.querySelector('#studentCreate');
    const openStudentForm = () => {
        if (!studentCreatePanel) return;
        studentCreatePanel.open = true;
        studentCreatePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        studentCreatePanel.querySelector('input[name="induk"]')?.focus({ preventScroll: true });
    };
    document.querySelector('[data-student-create-button]')?.addEventListener('click', openStudentForm);
    document.querySelector('[data-student-create-link]')?.addEventListener('click', (event) => {
        event.preventDefault();
        openStudentForm();
    });
    updateControls();

    if (canObserve) {
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) loadNextPage();
        }, { root: tableWrap, rootMargin: '0px 0px 220px 0px' });
        observer.observe(trigger);
    }
})();