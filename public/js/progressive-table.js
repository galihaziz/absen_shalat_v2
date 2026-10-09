(() => {
    document.querySelectorAll('[data-progressive-table]').forEach((tableWrap) => {
        const rows = tableWrap.querySelector('[data-progressive-rows]');
        const trigger = tableWrap.querySelector('[data-progressive-trigger]');
        const button = tableWrap.querySelector('[data-progressive-button]');
        const status = tableWrap.querySelector('[data-progressive-status]');
        const countLabel = tableWrap.closest('section, main')?.querySelector('[data-progressive-count]');

        if (!rows || !trigger || !button || !status) return;

        let nextPageUrl = tableWrap.dataset.nextPage || null;
        let isLoading = false;
        let loadedCount = Number(countLabel?.dataset.loadedCount) || rows.querySelectorAll('tr:not(.empty)').length;
        const canObserve = 'IntersectionObserver' in window;

        const updateControls = () => {
            trigger.hidden = !nextPageUrl;
            button.hidden = canObserve;
            button.disabled = false;
            if (countLabel) {
                countLabel.dataset.loadedCount = String(loadedCount);
                countLabel.textContent = nextPageUrl ? `${loadedCount} siswa dimuat` : `${loadedCount} siswa`;
            }
        };

        const loadNextPage = async () => {
            if (isLoading || !nextPageUrl) return;

            isLoading = true;
            button.disabled = true;
            status.textContent = 'Memuat data...';
            status.classList.add('is-loading');
            const skeletonRow = document.createElement('tr');
            skeletonRow.className = 'loading-skeleton-row';
            skeletonRow.setAttribute('aria-hidden', 'true');
            const skeletonCell = document.createElement('td');
            skeletonCell.colSpan = rows.closest('table').querySelectorAll('thead tr:last-child th').length;
            skeletonCell.innerHTML = '<span class="loading-shimmer"></span>';
            skeletonRow.append(skeletonCell);
            rows.append(skeletonRow);

            try {
                const response = await fetch(nextPageUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('Data gagal dimuat.');

                const result = await response.json();
                rows.insertAdjacentHTML('beforeend', result.html);
                loadedCount += Number(result.count) || 0;
                nextPageUrl = result.nextPageUrl;
                tableWrap.dataset.nextPage = nextPageUrl || '';
                status.textContent = '';
                updateControls();
            } catch {
                status.textContent = 'Gagal memuat. Tekan tombol untuk mencoba lagi.';
                button.hidden = false;
            } finally {
                skeletonRow.remove();
                status.classList.remove('is-loading');
                isLoading = false;
                button.disabled = false;
            }
        };

        button.addEventListener('click', loadNextPage);
        updateControls();

        if (canObserve) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) loadNextPage();
            }, { root: tableWrap, rootMargin: '0px 0px 220px 0px' });
            observer.observe(trigger);
        }
    });
})();