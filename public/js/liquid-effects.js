(() => {
    const navigation = document.querySelector('.nav-links');
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pointerQuery = window.matchMedia('(hover: hover) and (pointer: fine)');

    if (!navigation || motionQuery.matches || !pointerQuery.matches) return;

    const navItems = Array.from(navigation.querySelectorAll('a'));
    const svgNamespace = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNamespace, 'svg');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('width', '0');
    svg.setAttribute('height', '0');
    svg.style.cssText = 'position:fixed;width:0;height:0;overflow:hidden;pointer-events:none';

    const filter = document.createElementNS(svgNamespace, 'filter');
    filter.setAttribute('id', 'nav-liquid-text-warp');
    filter.setAttribute('x', '-35%');
    filter.setAttribute('y', '-35%');
    filter.setAttribute('width', '170%');
    filter.setAttribute('height', '170%');

    const turbulence = document.createElementNS(svgNamespace, 'feTurbulence');
    turbulence.setAttribute('type', 'fractalNoise');
    turbulence.setAttribute('baseFrequency', '0.035 0.09');
    turbulence.setAttribute('numOctaves', '2');
    turbulence.setAttribute('seed', '7');
    turbulence.setAttribute('result', 'liquid-noise');

    const displacement = document.createElementNS(svgNamespace, 'feDisplacementMap');
    displacement.setAttribute('in', 'SourceGraphic');
    displacement.setAttribute('in2', 'liquid-noise');
    displacement.setAttribute('scale', '0');
    displacement.setAttribute('xChannelSelector', 'R');
    displacement.setAttribute('yChannelSelector', 'G');

    filter.append(turbulence, displacement);
    svg.append(filter);
    document.body.append(svg);

    const clearLiquid = () => {
        const bubble = navigation.querySelector('.nav-glass-surface');
        bubble?.classList.remove('is-liquid-active');
        bubble?.style.removeProperty('--liquid-x');
        bubble?.style.removeProperty('--liquid-y');
        displacement.setAttribute('scale', '0');
        navItems.forEach((item) => {
            item.querySelectorAll('.nav-lens-glyph').forEach((glyph) => {
                glyph.classList.remove('is-liquid-text-active');
                glyph.style.removeProperty('--liquid-text-blur');
                glyph.style.removeProperty('--liquid-text-glow');
            });
        });
    };

    navigation.addEventListener('pointermove', (event) => {
        if (event.pointerType === 'touch') return;

        const item = event.target.closest('a');
        const bubble = navigation.querySelector('.nav-glass-surface');
        if (!item || !bubble) {
            clearLiquid();
            return;
        }

        const bounds = item.getBoundingClientRect();
        const x = Math.max(0, Math.min(100, ((event.clientX - bounds.left) / bounds.width) * 100));
        const y = Math.max(0, Math.min(100, ((event.clientY - bounds.top) / bounds.height) * 100));

        bubble.style.setProperty('--liquid-x', `${x.toFixed(2)}%`);
        bubble.style.setProperty('--liquid-y', `${y.toFixed(2)}%`);
        bubble.classList.add('is-liquid-active');

        let maxInfluence = 0;
        navItems.forEach((navItem) => {
            navItem.querySelectorAll('.nav-lens-glyph').forEach((glyph) => {
                const rect = glyph.getBoundingClientRect();
                const distance = Math.hypot(event.clientX - (rect.left + rect.width / 2), event.clientY - (rect.top + rect.height / 2));
                const influence = navItem === item ? Math.max(0, 1 - distance / 92) : 0;

                glyph.classList.toggle('is-liquid-text-active', influence > 0.04);
                if (influence > 0.04) {
                    glyph.style.setProperty('--liquid-text-blur', `${(0.2 + influence * 1.25).toFixed(2)}px`);
                    glyph.style.setProperty('--liquid-text-glow', (influence * 0.8).toFixed(2));
                } else {
                    glyph.style.removeProperty('--liquid-text-blur');
                    glyph.style.removeProperty('--liquid-text-glow');
                }

                maxInfluence = Math.max(maxInfluence, influence);
            });
        });

        displacement.setAttribute('scale', String(Math.round(maxInfluence * 14)));
    }, { passive: true });

    navigation.addEventListener('pointerleave', clearLiquid, { passive: true });
    window.addEventListener('blur', clearLiquid);
    motionQuery.addEventListener('change', (event) => {
        if (event.matches) clearLiquid();
    });
})();
