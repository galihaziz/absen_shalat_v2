(() => {
    const compactViewport = window.matchMedia('(max-width: 600px)').matches;

    document.querySelectorAll('[data-class-list-toggle]').forEach((button) => {
        const list = document.getElementById(button.dataset.listId);
        const additionalClasses = list?.querySelectorAll('[data-class-list-extra]');
        if (!additionalClasses?.length) return;

        button.addEventListener('click', () => {
            const showAll = button.getAttribute('aria-expanded') !== 'true';
            additionalClasses.forEach((item) => { item.hidden = !showAll; });
            button.setAttribute('aria-expanded', String(showAll));
            button.textContent = showAll ? button.dataset.labelExpanded : button.dataset.labelCollapsed;
        });
    });

    document.querySelectorAll('.table-wrap table').forEach((table, tableIndex) => {
        const headerRow = table.tHead?.rows[0];
        const nameHeader = headerRow && [...headerRow.cells]
            .find((header) => header.textContent.trim().toLowerCase() === 'nama');

        if (!nameHeader) return;

        const nameIndex = [...headerRow.cells].indexOf(nameHeader);

        const wrapper = table.closest('.table-wrap');
        if (!wrapper) return;

        const isAttendance = table.classList.contains('attendance-table');
        const min = isAttendance ? 110 : 130;
        const max = compactViewport ? 280 : 380;
        const defaultWidth = isAttendance && compactViewport ? 142 : 220;
        const storageKey = `rekap-name-width:${window.location.pathname}:${tableIndex}`;
        const savedWidth = Number.parseInt(localStorage.getItem(storageKey), 10);
        const initialWidth = Number.isFinite(savedWidth)
            ? Math.max(min, Math.min(max, savedWidth))
            : defaultWidth;

        nameHeader.dataset.nameColumn = 'true';
        [...table.tBodies].forEach((body) => body.querySelectorAll('tr').forEach((row) => {
            const cell = row.children[nameIndex];
            if (!cell || cell.colSpan > 1) return;

            cell.dataset.nameColumn = 'true';
            if (cell.tagName === 'TD' && !cell.title) {
                cell.title = cell.textContent.trim();
            }
        }));

        const control = document.createElement('label');
        const slider = document.createElement('input');
        const output = document.createElement('output');
        const controlId = `tableNameWidth${tableIndex}`;

        control.className = 'table-name-width-control';
        control.htmlFor = controlId;
        slider.id = controlId;
        slider.type = 'range';
        slider.min = String(min);
        slider.max = String(max);
        slider.step = '10';
        slider.value = String(initialWidth);
        slider.setAttribute('aria-label', 'Lebar kolom nama');
        output.htmlFor = controlId;

        function applyWidth() {
            const width = Number(slider.value);
            wrapper.style.setProperty('--table-name-column-width', `${width}px`);
            output.value = `${width} px`;
            localStorage.setItem(storageKey, String(width));
        }

        slider.addEventListener('input', applyWidth);
        control.append('Lebar kolom nama', slider, output);
        wrapper.parentElement?.insertBefore(control, wrapper);
        applyWidth();
    });
})();