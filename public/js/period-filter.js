document.querySelectorAll('[data-period-filter]').forEach((form) => {
    const periodSelect = form.querySelector('select[name="periode"], select[name="tipe"]');
    const monthFields = form.querySelectorAll('[data-period-month]');
    const weekFields = form.querySelectorAll('[data-period-week]');

    if (!periodSelect) return;

    function updatePeriodFields() {
        const isWeekly = periodSelect.value === 'minggu';

        monthFields.forEach((field) => {
            field.hidden = isWeekly;
            field.querySelectorAll('input, select').forEach((input) => {
                input.disabled = isWeekly;
            });
        });

        weekFields.forEach((field) => {
            field.hidden = !isWeekly;
            field.querySelectorAll('input, select').forEach((input) => {
                input.disabled = !isWeekly;
            });
        });
    }

    periodSelect.addEventListener('change', updatePeriodFields);
    updatePeriodFields();
});