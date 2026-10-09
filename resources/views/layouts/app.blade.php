<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Rekap Absensi Solat')</title>
    @php($irmaLogoUrl = asset('images/irma-logo.png').'?v='.md5_file(public_path('images/irma-logo.png')))
    <link rel="icon" type="image/png" href="{{ $irmaLogoUrl }}">
    <script>
        try {
            const savedTheme = localStorage.getItem('rekap-theme');
            const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = ['dark', 'light'].includes(savedTheme) ? savedTheme : preferredTheme;
        } catch {
            document.documentElement.dataset.theme = 'light';
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ md5_file(public_path('css/app.css')) }}">
    <script src="{{ asset('js/hyalite.js') }}?v={{ md5_file(public_path('js/hyalite.js')) }}" defer></script>
    <script src="{{ asset('js/table-name-width.js') }}?v={{ md5_file(public_path('js/table-name-width.js')) }}" defer></script>
    <script src="{{ asset('js/period-filter.js') }}?v={{ md5_file(public_path('js/period-filter.js')) }}" defer></script>
    <script src="{{ asset('js/page-motion.js') }}?v={{ md5_file(public_path('js/page-motion.js')) }}" defer></script>
    <script src="{{ asset('js/liquid-effects.js') }}?v={{ md5_file(public_path('js/liquid-effects.js')) }}" defer></script>
    <script src="{{ asset('js/progressive-table.js') }}?v={{ md5_file(public_path('js/progressive-table.js')) }}" defer></script>
    <script src="{{ asset('js/form-enhancements.js') }}?v={{ md5_file(public_path('js/form-enhancements.js')) }}" defer></script>
</head>
<body>
@php($studentDomain = strtolower((string) config('app.student_domain')))
@php($isStudentRoot = $studentDomain !== '' && strtolower(request()->getHost()) === $studentDomain && request()->path() === '/')
@unless($isStudentRoot)
<header class="topbar {{ auth()->check() ? 'topbar-authenticated' : 'topbar-guest' }}">
    <a class="brand" href="{{ route('attendance.index') }}">
        <img class="brand-logo" src="{{ $irmaLogoUrl }}" alt="Logo IRMA">
        <span>Rekap Absensi Solat</span>
    </a>
    @auth
        @php($currentUser = auth()->user())
        @php($currentRole = $currentUser->getAttribute('role'))
        <nav class="nav-links" id="mainNavigation">
            <a href="{{ route('attendance.index') }}" class="{{ request()->routeIs('attendance.index') ? 'is-active' : '' }}"><span class="nav-lens-label">Absensi</span></a>
            <a href="{{ route('attendance.scan') }}" class="{{ request()->routeIs('attendance.scan') ? 'is-active' : '' }}"><span class="nav-lens-label">Scan QR</span></a>
            @if(in_array($currentRole, ['admin', 'kesiswaan'], true))
                <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.index') ? 'is-active' : '' }}"><span class="nav-lens-label">Laporan</span></a>
                <a href="{{ route('reports.alpha') }}" class="{{ request()->routeIs('reports.alpha') ? 'is-active' : '' }}"><span class="nav-lens-label">Siswa Alpha</span></a>
                <a href="{{ route('students.index') }}" class="{{ request()->routeIs('students.index') ? 'is-active' : '' }}"><span class="nav-lens-label">Data Siswa</span></a>
                <a href="{{ route('students.qr.selection') }}" class="{{ request()->routeIs('students.qr.selection') ? 'is-active' : '' }}"><span class="nav-lens-label">Pilih Kartu Manual</span></a>
            @endif
            @if($currentRole === 'admin')
                <a href="{{ route('students.import') }}" class="{{ request()->routeIs('students.import') ? 'is-active' : '' }}"><span class="nav-lens-label">Import</span></a>
                <a href="{{ route('student-card-templates.index') }}" class="{{ request()->routeIs('student-card-templates.index') ? 'is-active' : '' }}"><span class="nav-lens-label">Template Kartu</span></a>
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.index') ? 'is-active' : '' }}"><span class="nav-lens-label">Pengguna</span></a>
            @endif
            <a href="{{ route('attendance.print') }}" class="{{ request()->routeIs('attendance.print') ? 'is-active' : '' }}"><span class="nav-lens-label">Cetak A4</span></a>
        </nav>
        <div class="account">
            <span>{{ $currentUser->getAttribute('nama') }} · {{ $currentRole }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button quiet" type="submit">Keluar</button></form>
        </div>
        <button class="button quiet theme-toggle" type="button" data-theme-toggle aria-label="Aktifkan mode gelap" aria-pressed="false">
            <span class="theme-toggle-indicator" aria-hidden="true"></span>
            <span class="theme-toggle-icon theme-toggle-sun" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path></svg></span>
            <span class="theme-toggle-icon theme-toggle-moon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20.2 15.5A8.5 8.5 0 0 1 8.5 3.8 8.6 8.6 0 1 0 20.2 15.5Z"></path></svg></span>
        </button>
        <button class="mobile-nav-toggle" type="button" aria-controls="mainNavigation" aria-expanded="false">
            <span class="menu-glyph" aria-hidden="true"><span></span><span></span><span></span></span>
            <span>Menu</span>
        </button>
    @else
        <button class="button quiet theme-toggle guest-theme-toggle" type="button" data-theme-toggle aria-label="Aktifkan mode gelap" aria-pressed="false">
            <span class="theme-toggle-indicator" aria-hidden="true"></span>
            <span class="theme-toggle-icon theme-toggle-sun" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path></svg></span>
            <span class="theme-toggle-icon theme-toggle-moon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20.2 15.5A8.5 8.5 0 0 1 8.5 3.8 8.6 8.6 0 1 0 20.2 15.5Z"></path></svg></span>
        </button>
    @endauth
</header>
@endunless
@if($isStudentRoot)
    <button class="button quiet theme-toggle theme-toggle-floating" type="button" data-theme-toggle aria-label="Aktifkan mode gelap" aria-pressed="false">
        <span class="theme-toggle-indicator" aria-hidden="true"></span>
        <span class="theme-toggle-icon theme-toggle-sun" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path></svg></span>
        <span class="theme-toggle-icon theme-toggle-moon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20.2 15.5A8.5 8.5 0 0 1 8.5 3.8 8.6 8.6 0 1 0 20.2 15.5Z"></path></svg></span>
    </button>
@endif
<main class="page">
    @auth
        @php($greetingHour = now()->hour)
        @php($greeting = $greetingHour < 11 ? 'Selamat pagi' : ($greetingHour < 15 ? 'Selamat siang' : ($greetingHour < 18 ? 'Selamat sore' : 'Selamat malam')))
        @php($weekdayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'])
        @php($monthNamesId = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'])
        <div class="personal-greeting">
            <strong>{{ $greeting }}, {{ auth()->user()->getAttribute('nama') }}</strong>
            <span>{{ $weekdayNames[now()->dayOfWeek] }}, {{ now()->day }} {{ $monthNamesId[now()->month] }} {{ now()->year }} · {{ auth()->user()->getAttribute('role') }}</span>
        </div>
    @endauth
    @if($isStudentRoot)
        <a class="student-root-brand" href="{{ route('student.domain.root') }}">
            <img src="{{ $irmaLogoUrl }}" alt="Logo IRMA">
            <span>AL-HIDAYAH<br>REKAP ABSENSI SOLAT</span>
        </a>
    @endif
    @if(session('status')) <div class="notice success">{{ session('status') }}</div> @endif
    @if(session('warning')) <div class="notice warning" role="status">{{ session('warning') }}</div> @endif
    @if($errors->any()) <div class="notice error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    @yield('content')
</main>
@stack('scripts')
<script>
const mobileMenuButton = document.querySelector('.mobile-nav-toggle');
const mainNavigation = document.getElementById('mainNavigation');
const sharedCsrfToken = document.querySelector('meta[name="csrf-token"]').content;
const themeToggleButtons = Array.from(document.querySelectorAll('[data-theme-toggle]'));

const syncThemeToggle = () => {
    const isDark = document.documentElement.dataset.theme === 'dark';
    const action = isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
    themeToggleButtons.forEach((button) => {
        button.classList.toggle('is-dark', isDark);
        button.setAttribute('aria-pressed', String(isDark));
        button.setAttribute('aria-label', action);
        button.title = `${isDark ? 'Tema gelap aktif' : 'Tema terang aktif'}. ${action}.`;
    });
};

themeToggleButtons.forEach((button) => button.addEventListener('click', () => {
    const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = nextTheme;
    try {
        localStorage.setItem('rekap-theme', nextTheme);
    } catch {}
    syncThemeToggle();
    button.classList.remove('is-changing');
    void button.offsetWidth;
    button.classList.add('is-changing');
    window.setTimeout(() => button.classList.remove('is-changing'), 720);
}));

syncThemeToggle();

mobileMenuButton?.addEventListener('click', () => {
    const isExpanded = mobileMenuButton.getAttribute('aria-expanded') === 'true';
    mobileMenuButton.setAttribute('aria-expanded', String(!isExpanded));
    mainNavigation?.classList.toggle('is-open', !isExpanded);
});

const closeMobileNavigation = () => {
    mobileMenuButton?.setAttribute('aria-expanded', 'false');
    mainNavigation?.classList.remove('is-open');
};

mainNavigation?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
    closeMobileNavigation();
}));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMobileNavigation();
});

document.addEventListener('click', (event) => {
    if (!mainNavigation?.classList.contains('is-open')) return;
    if (mainNavigation.contains(event.target) || mobileMenuButton?.contains(event.target)) return;
    closeMobileNavigation();
});

window.matchMedia('(min-width: 901px)').addEventListener('change', (event) => {
    if (event.matches) closeMobileNavigation();
});

const navLensMedia = window.matchMedia('(hover: hover) and (pointer: fine)');
const navMotionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');
const navLensItems = Array.from(mainNavigation?.querySelectorAll('a') ?? []);
const navLensLabels = navLensItems.map((item) => item.querySelector('.nav-lens-label'));

if (mainNavigation && !navMotionMedia.matches) {
    const navGlassSurface = document.createElement('span');
    navGlassSurface.className = 'nav-glass-surface';
    navGlassSurface.setAttribute('aria-hidden', 'true');
    mainNavigation.prepend(navGlassSurface);

    navLensItems.forEach((item, index) => {
        const label = navLensLabels[index];
        if (!label) return;
        const text = label.textContent ?? '';
        item.setAttribute('aria-label', text);

        if (!item.querySelector('.nav-lens-glyph')) {
            label.innerHTML = '';
            Array.from(text).forEach((character) => {
                const glyph = document.createElement('span');
                glyph.className = 'nav-lens-glyph';
                glyph.textContent = character === ' ' ? '\u00a0' : character;
                if (character === ' ') glyph.classList.add('is-space');
                label.appendChild(glyph);
            });
        }
    });

    let glassFrame = 0;
    let glassTarget = null;
    let glassCurrent = null;
    let pointerX = 0;
    let pointerY = 0;
    let hoveredItem = null;

    const clearGlyphEffects = (item) => {
        item?.querySelectorAll('.nav-lens-glyph').forEach((glyph) => {
            glyph.style.transform = '';
            glyph.style.textShadow = '';
        });
    };

    const animateNavGlass = () => {
        glassFrame = 0;
        if (!glassTarget) return;

        const navRect = mainNavigation.getBoundingClientRect();
        const targetRect = glassTarget.getBoundingClientRect();
        const target = {
            x: targetRect.left - navRect.left - mainNavigation.clientLeft,
            y: targetRect.top - navRect.top - mainNavigation.clientTop,
            width: targetRect.width,
            height: targetRect.height
        };

        if (!glassCurrent) glassCurrent = {...target};
        glassCurrent.x += (target.x - glassCurrent.x) * 0.24;
        glassCurrent.y += (target.y - glassCurrent.y) * 0.24;
        glassCurrent.width += (target.width - glassCurrent.width) * 0.24;
        glassCurrent.height += (target.height - glassCurrent.height) * 0.24;

        navGlassSurface.style.transform = `translate3d(${glassCurrent.x}px, ${glassCurrent.y}px, 0)`;
        navGlassSurface.style.width = `${glassCurrent.width}px`;
        navGlassSurface.style.height = `${glassCurrent.height}px`;
        navGlassSurface.style.setProperty('--pointer-x', `${Math.max(0, Math.min(100, ((pointerX - targetRect.left) / targetRect.width) * 100))}%`);
        navGlassSurface.style.setProperty('--pointer-y', `${Math.max(0, Math.min(100, ((pointerY - targetRect.top) / targetRect.height) * 100))}%`);

        if (hoveredItem) {
            hoveredItem.querySelectorAll('.nav-lens-glyph').forEach((glyph) => {
                const rect = glyph.getBoundingClientRect();
                const distance = Math.hypot(pointerX - (rect.left + rect.width / 2), pointerY - (rect.top + rect.height / 2));
                const influence = Math.max(0, 1 - distance / 120);
                glyph.style.transform = `translateY(${-influence * 2}px)`;
                glyph.style.textShadow = `0 0 ${8 * influence}px rgba(255,255,255,${0.35 + influence * 0.55})`;
            });
        }

        const unsettled = Math.abs(target.x - glassCurrent.x) > 0.2 || Math.abs(target.y - glassCurrent.y) > 0.2 || Math.abs(target.width - glassCurrent.width) > 0.2 || Math.abs(target.height - glassCurrent.height) > 0.2;
        if (unsettled) glassFrame = requestAnimationFrame(animateNavGlass);
    };

    const moveNavGlass = (item, x, y) => {
        if (hoveredItem !== item) clearGlyphEffects(hoveredItem);
        hoveredItem = item;
        glassTarget = item;
        pointerX = x;
        pointerY = y;
        mainNavigation.classList.add('is-lens-visible');
        if (!glassFrame) glassFrame = requestAnimationFrame(animateNavGlass);
    };

    const returnNavGlassToActiveItem = () => {
        clearGlyphEffects(hoveredItem);
        hoveredItem = null;
        const activeItem = navLensItems.find((item) => item.classList.contains('is-active'));
        if (!activeItem) {
            mainNavigation.classList.remove('is-lens-visible');
            glassTarget = null;
            return;
        }

        const rect = activeItem.getBoundingClientRect();
        glassTarget = activeItem;
        pointerX = rect.left + rect.width / 2;
        pointerY = rect.top + rect.height / 2;
        mainNavigation.classList.add('is-lens-visible');
        if (!glassFrame) glassFrame = requestAnimationFrame(animateNavGlass);
    };

    if (navLensMedia.matches) {
        mainNavigation.addEventListener('pointermove', (event) => {
            const item = event.target.closest('a');
            if (item && navLensItems.includes(item)) moveNavGlass(item, event.clientX, event.clientY);
        });
    } else {
        mainNavigation.addEventListener('pointerdown', (event) => {
            const item = event.target.closest('a');
            if (item && navLensItems.includes(item)) moveNavGlass(item, event.clientX, event.clientY);
        });
    }

    mainNavigation.addEventListener('pointerleave', returnNavGlassToActiveItem);

    mainNavigation.addEventListener('focusin', (event) => {
        const item = event.target.closest('a');
        if (!item || !navLensItems.includes(item)) return;
        const rect = item.getBoundingClientRect();
        moveNavGlass(item, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    mainNavigation.addEventListener('focusout', (event) => {
        if (!mainNavigation.contains(event.relatedTarget)) returnNavGlassToActiveItem();
    });

    returnNavGlassToActiveItem();
}

    const uiLensTargets = '.topbar button:not(.account .button), .topbar .button:not(.account .button)';

if (!navMotionMedia.matches) {
    let uiLensFrame = 0;
    let uiLensTarget = null;
    let uiPointerX = 0;
    let uiPointerY = 0;
    let uiLensTimer = 0;

    const prepareUiLensText = (target) => {
        if (target.dataset.uiLensReady) return;

        const textNodes = [];
        if (target.matches('label')) {
            textNodes.push(...Array.from(target.childNodes).filter((node) => node.nodeType === Node.TEXT_NODE && node.nodeValue.trim()));
        } else {
            const walker = document.createTreeWalker(target, NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => node.parentElement.closest('input, select, textarea, option, script, style')
                    ? NodeFilter.FILTER_REJECT
                    : NodeFilter.FILTER_ACCEPT
            });
            while (walker.nextNode()) {
                if (walker.currentNode.nodeValue.trim()) textNodes.push(walker.currentNode);
            }
        }

        const accessibleText = textNodes.map((node) => node.nodeValue).join('');
        if (target.matches('.button') && accessibleText && !target.hasAttribute('aria-label')) {
            target.setAttribute('aria-label', accessibleText);
        }

        textNodes.forEach((textNode) => {
            const fragment = document.createDocumentFragment();
            Array.from(textNode.nodeValue).forEach((character) => {
                const glyph = document.createElement('span');
                glyph.className = 'ui-lens-glyph';
                glyph.textContent = character;
                if (/\s/.test(character)) glyph.classList.add('is-space');
                fragment.appendChild(glyph);
            });

            if (target.matches('label')) {
                const labelText = document.createElement('span');
                labelText.className = 'ui-lens-label-text';
                labelText.appendChild(fragment);
                textNode.replaceWith(labelText);
            } else {
                textNode.replaceWith(fragment);
            }
        });

        target.dataset.uiLensReady = 'true';
    };

    const clearUiLens = () => {
        if (!uiLensTarget) return;
        uiLensTarget.classList.remove('is-pointer-lens');
        uiLensTarget.style.setProperty('--pointer-x', '50%');
        uiLensTarget.style.setProperty('--pointer-y', '50%');
        uiLensTarget.querySelectorAll('.ui-lens-glyph').forEach((glyph) => {
            glyph.style.transform = '';
            glyph.style.textShadow = '';
        });
        uiLensTarget = null;
    };

    const applyUiLens = () => {
        uiLensFrame = 0;
        if (!uiLensTarget) return;

        const bounds = uiLensTarget.getBoundingClientRect();
        uiLensTarget.style.setProperty('--pointer-x', `${Math.max(0, Math.min(100, ((uiPointerX - bounds.left) / bounds.width) * 100))}%`);
        uiLensTarget.style.setProperty('--pointer-y', `${Math.max(0, Math.min(100, ((uiPointerY - bounds.top) / bounds.height) * 100))}%`);

        uiLensTarget.querySelectorAll('.ui-lens-glyph').forEach((glyph) => {
            const rect = glyph.getBoundingClientRect();
            const distance = Math.hypot(uiPointerX - (rect.left + rect.width / 2), uiPointerY - (rect.top + rect.height / 2));
            const influence = Math.max(0, 1 - distance / 120);
            glyph.style.transform = `translateY(${-influence * 2}px)`;
            glyph.style.textShadow = `0 0 ${8 * influence}px rgba(255,255,255,${0.35 + influence * 0.55})`;
        });
    };

    const moveUiLens = (target, x, y) => {
        if (uiLensTarget !== target) {
            clearUiLens();
            uiLensTarget = target;
            prepareUiLensText(target);
            target.classList.add('is-pointer-lens');
        }

        uiPointerX = x;
        uiPointerY = y;
        if (!uiLensFrame) uiLensFrame = requestAnimationFrame(applyUiLens);
    };

    const uiLensRoot = document.body;

    if (navLensMedia.matches) {
        uiLensRoot.addEventListener('pointermove', (event) => {
            const target = event.target.closest(uiLensTargets);
            if (target) moveUiLens(target, event.clientX, event.clientY);
            else clearUiLens();
        });

        uiLensRoot.addEventListener('pointerleave', clearUiLens);
    } else {
        uiLensRoot.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' || event.target.closest('input, select, textarea')) return;
            const target = event.target.closest(uiLensTargets);
            if (!target) return;
            moveUiLens(target, event.clientX, event.clientY);
            window.clearTimeout(uiLensTimer);
            uiLensTimer = window.setTimeout(clearUiLens, 700);
        });
    }

    uiLensRoot.addEventListener('focusin', (event) => {
        const target = event.target.closest(uiLensTargets);
        if (!target) return;
        const bounds = target.getBoundingClientRect();
        moveUiLens(target, bounds.left + bounds.width / 2, bounds.top + bounds.height / 2);
    });

    uiLensRoot.addEventListener('focusout', (event) => {
        if (!(event.relatedTarget instanceof Element) || !event.relatedTarget.closest(uiLensTargets)) clearUiLens();
    });
}

document.querySelectorAll('[data-attendance-delete]').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const monthName = form.elements.bulan.selectedOptions[0].textContent;
    const selectedClass = form.elements.kelas.value;
    const scope = selectedClass === 'semua' ? 'semua rombel' : 'rombel ' + selectedClass;
    if (!confirm(`Hapus seluruh catatan absensi untuk ${scope} pada ${monthName} ${form.elements.tahun.value}? Data siswa tidak dihapus.`)) return;

    const button = form.querySelector('button[type="submit"]');
    const originalLabel = button.textContent;
    const feedback = form.parentElement.querySelector('[data-delete-state]');
    button.disabled = true;
    button.textContent = 'Menghapus...';
    feedback.textContent = '';

    try {
        const response = await fetch(form.dataset.endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': sharedCsrfToken, 'Accept': 'application/json'},
            body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Data absensi gagal dihapus.');
        feedback.textContent = `${result.deleted} catatan absensi berhasil dihapus.`;
    } catch (error) {
        feedback.textContent = error.message || 'Data absensi gagal dihapus.';
    } finally {
        button.disabled = false;
        button.textContent = originalLabel;
    }
}));
</script>
</body>
</html>