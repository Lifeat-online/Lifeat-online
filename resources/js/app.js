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

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initReveal();
});
