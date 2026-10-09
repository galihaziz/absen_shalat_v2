(() => {
    const grid = document.querySelector('[data-class-progress-grid]');
    const gradeFilter = document.querySelector('[data-progress-grade-filter]');
    const majorFilter = document.querySelector('[data-progress-major-filter]');
    const emptyState = document.querySelector('[data-class-progress-empty]');

    if (!grid || !gradeFilter || !majorFilter || !emptyState) return;

    const cards = Array.from(grid.querySelectorAll('[data-class-progress-card]'));

    const filterCards = () => {
        let visibleCount = 0;

        cards.forEach((card) => {
            const matchesGrade = !gradeFilter.value || card.dataset.grade === gradeFilter.value;
            const matchesMajor = !majorFilter.value || card.dataset.major === majorFilter.value;
            card.hidden = !matchesGrade || !matchesMajor;
            if (!card.hidden) visibleCount++;
        });

        emptyState.hidden = visibleCount > 0;
    };

    gradeFilter.addEventListener('change', filterCards);
    majorFilter.addEventListener('change', filterCards);
})();
