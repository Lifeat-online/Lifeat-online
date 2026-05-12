import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const themeKey = 'life-theme';

const getPreferredTheme = () => {
    const stored = localStorage.getItem(themeKey);
    if (stored === 'dark' || stored === 'light') return stored;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const syncThemeAssets = (theme) => {
    document.querySelectorAll('[data-theme-logo]').forEach((logo) => {
        const light = logo.getAttribute('data-logo-light');
        const dark = logo.getAttribute('data-logo-dark');
        if (!light || !dark) return;
        logo.setAttribute('src', theme === 'dark' ? dark : light);
    });

    document.querySelectorAll('[data-theme-icon-sun]').forEach((icon) => {
        icon.style.display = theme === 'dark' ? 'none' : 'block';
    });

    document.querySelectorAll('[data-theme-icon-moon]').forEach((icon) => {
        icon.style.display = theme === 'dark' ? 'block' : 'none';
    });
};

const applyTheme = (theme) => {
    const root = document.documentElement;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    localStorage.setItem(themeKey, theme);
    syncThemeAssets(theme);
};

const initThemeToggle = () => {
    const root = document.documentElement;
    const initialTheme = root.dataset.theme === 'dark' || root.dataset.theme === 'light' ? root.dataset.theme : getPreferredTheme();
    applyTheme(initialTheme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    });
};

const initReveal = () => {
    const items = Array.from(document.querySelectorAll('[data-reveal]'));
    if (!items.length) return;

    items.forEach((el) => el.classList.add('lp-reveal'));

    if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-revealed'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -10% 0px' },
    );

    items.forEach((el) => observer.observe(el));
};

const prefersReducedMotion = () => {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
};

const initSectionHighlighting = () => {
    const links = Array.from(document.querySelectorAll('[data-nav-link]')).filter((a) => a instanceof HTMLAnchorElement);
    if (!links.length) return;

    const items = links
        .map((a) => {
            try {
                const url = new URL(a.href, window.location.href);
                const samePage = url.origin === window.location.origin && url.pathname === window.location.pathname;
                const id = url.hash ? url.hash.slice(1) : '';
                if (!samePage || !id) return null;
                const el = document.getElementById(id);
                if (!el) return null;
                return { a, el, id };
            } catch (_) {
                return null;
            }
        })
        .filter(Boolean);

    if (!items.length) return;

    const setActive = (id) => {
        items.forEach(({ a, id: itemId }) => {
            a.classList.toggle('is-section-active', itemId === id);
        });
    };

    if (!('IntersectionObserver' in window)) {
        const onScroll = () => {
            const fromTop = window.scrollY + 140;
            const current = items
                .map(({ el, id }) => ({ id, top: el.getBoundingClientRect().top + window.scrollY }))
                .filter(({ top }) => top <= fromTop)
                .sort((a, b) => b.top - a.top)[0];
            if (current) setActive(current.id);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((e) => e.isIntersecting)
                .sort((a, b) => (b.intersectionRatio || 0) - (a.intersectionRatio || 0))[0];
            if (!visible) return;
            const id = visible.target.getAttribute('id');
            if (id) setActive(id);
        },
        { rootMargin: '-30% 0px -55% 0px', threshold: [0.08, 0.18, 0.28, 0.38, 0.5, 0.65] },
    );

    items.forEach(({ el }) => observer.observe(el));
};

const initSmoothScroll = () => {
    const links = Array.from(document.querySelectorAll('a[href*="#"]')).filter((a) => a instanceof HTMLAnchorElement);
    if (!links.length) return;

    links.forEach((a) => {
        a.addEventListener('click', (e) => {
            const href = a.getAttribute('href') || '';
            if (!href.includes('#')) return;
            let url;
            try {
                url = new URL(href, window.location.href);
            } catch (_) {
                return;
            }
            if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return;
            const id = url.hash ? url.hash.slice(1) : '';
            if (!id) return;
            const target = document.getElementById(id);
            if (!target) return;

            e.preventDefault();
            document.dispatchEvent(new CustomEvent('lp:navigate'));

            target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
            history.pushState(null, '', `#${id}`);

            const previousTabIndex = target.getAttribute('tabindex');
            target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
            if (previousTabIndex === null) {
                setTimeout(() => target.removeAttribute('tabindex'), 0);
            } else {
                target.setAttribute('tabindex', previousTabIndex);
            }
        });
    });
};

const initPublicNavigation = () => {
    const root = document.querySelector('[data-nav-root]');
    if (!root) return;

    const openBtn = root.querySelector('[data-nav-toggle]');
    const closeBtn = root.querySelector('[data-nav-close]');
    const overlay = root.querySelector('[data-nav-overlay]');
    const drawer = root.querySelector('[data-nav-drawer]');
    if (!openBtn || !overlay || !drawer) return;

    let lastFocus = null;
    let closingTimer = null;

    const focusables = () => {
        return Array.from(
            drawer.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ),
        ).filter((el) => el instanceof HTMLElement && !el.hasAttribute('hidden'));
    };

    const setOpenState = (open) => {
        if (closingTimer) {
            clearTimeout(closingTimer);
            closingTimer = null;
        }

        if (open) {
            lastFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.documentElement.classList.add('lp-nav-open');
            overlay.hidden = false;
            drawer.hidden = false;
            openBtn.setAttribute('aria-expanded', 'true');
            const first = focusables()[0];
            if (first) first.focus();
            return;
        }

        document.documentElement.classList.remove('lp-nav-open');
        openBtn.setAttribute('aria-expanded', 'false');
        closingTimer = window.setTimeout(() => {
            overlay.hidden = true;
            drawer.hidden = true;
            closingTimer = null;
        }, 240);
        if (lastFocus) lastFocus.focus();
    };

    const isOpen = () => document.documentElement.classList.contains('lp-nav-open');

    openBtn.addEventListener('click', () => setOpenState(!isOpen()));
    if (closeBtn) closeBtn.addEventListener('click', () => setOpenState(false));
    overlay.addEventListener('click', () => setOpenState(false));

    document.addEventListener('lp:navigate', () => setOpenState(false));

    root.querySelectorAll('[data-nav-link]').forEach((link) => {
        link.addEventListener('click', () => {
            if (isOpen()) setOpenState(false);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (!isOpen()) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            setOpenState(false);
            return;
        }
        if (e.key !== 'Tab') return;
        const els = focusables();
        if (!els.length) return;
        const first = els[0];
        const last = els[els.length - 1];
        const active = document.activeElement;
        if (e.shiftKey && active === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && active === last) {
            e.preventDefault();
            first.focus();
        }
    });

    window.addEventListener(
        'resize',
        () => {
            if (window.innerWidth >= 768 && isOpen()) setOpenState(false);
        },
        { passive: true },
    );
};

const initScopedServiceWorker = () => {
    if (!('serviceWorker' in navigator)) return;

    const url = document.querySelector('meta[name="lp-sw-url"]')?.getAttribute('content');
    const scope = document.querySelector('meta[name="lp-sw-scope"]')?.getAttribute('content');
    if (!url || !scope) return;

    navigator.serviceWorker
        .getRegistrations()
        .then((regs) => {
            regs.forEach((r) => {
                if (r.scope === `${location.origin}/`) {
                    r.unregister().catch(() => {});
                }
            });
        })
        .catch(() => {});

    navigator.serviceWorker.register(url, { scope }).catch(() => {});
};

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initReveal();
    initPublicNavigation();
    initSmoothScroll();
    initSectionHighlighting();
    initScopedServiceWorker();
});
