import './bootstrap';

import Alpine from 'alpinejs';
import { createClient } from '@supabase/supabase-js';

window.Alpine = Alpine;

Alpine.start();

const themeKey = 'life-theme';

const readStoredTheme = () => {
    try {
        return localStorage.getItem(themeKey);
    } catch (_) {
        return null;
    }
};

const writeStoredTheme = (theme) => {
    try {
        localStorage.setItem(themeKey, theme);
    } catch (_) {
        // Some mobile browsers can block storage in private contexts.
    }
};

const getPreferredTheme = () => {
    const stored = readStoredTheme();
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
    writeStoredTheme(theme);
    syncThemeAssets(theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    });
};

const initThemeToggle = () => {
    const root = document.documentElement;
    const initialTheme = root.dataset.theme === 'dark' || root.dataset.theme === 'light' ? root.dataset.theme : getPreferredTheme();
    applyTheme(initialTheme);

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-theme-toggle]') : null;
        if (!button) return;

        event.preventDefault();
        applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
        document.dispatchEvent(new CustomEvent('life:theme-changed', { detail: { theme: root.dataset.theme } }));
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

const initServiceWorker = () => {
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
};

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
};

const initPushNotifications = () => {
    const buttons = Array.from(document.querySelectorAll('[data-push-toggle]')).filter((button) => button instanceof HTMLButtonElement);
    const toneButtons = Array.from(document.querySelectorAll('[data-push-tone-preview]')).filter((button) => button instanceof HTMLButtonElement);
    if (!buttons.length && !toneButtons.length) return;

    const key = document.querySelector('meta[name="webpush-vapid-public-key"]')?.getAttribute('content');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const supported = key && csrf && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    if (!supported) return;

    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;

    let audioContext = null;
    const toneMap = {
        chime: [880, 1174],
        bell: [784, 988, 1318],
        urgent: [880, 880, 880],
        soft: [523, 659],
    };

    const ensureAudioContext = async () => {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return null;
        audioContext ||= new AudioContextClass();
        if (audioContext.state === 'suspended') await audioContext.resume();
        return audioContext;
    };

    const playTone = async (tone = 'chime') => {
        const context = await ensureAudioContext();
        if (!context) return;

        const notes = toneMap[tone] || toneMap.chime;
        const start = context.currentTime;

        notes.forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const noteStart = start + index * 0.16;

            oscillator.type = tone === 'soft' ? 'sine' : 'triangle';
            oscillator.frequency.setValueAtTime(frequency, noteStart);
            gain.gain.setValueAtTime(0.0001, noteStart);
            gain.gain.exponentialRampToValueAtTime(tone === 'urgent' ? 0.12 : 0.08, noteStart + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, noteStart + 0.14);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(noteStart);
            oscillator.stop(noteStart + 0.16);
        });
    };

    const setButtonState = (label, disabled = false) => {
        buttons.forEach((button) => {
            button.hidden = false;
            button.textContent = label;
            button.disabled = disabled;
            button.setAttribute('aria-live', 'polite');
        });
    };

    const contentEncoding = () => {
        if (window.PushManager.supportedContentEncodings?.includes('aes128gcm')) return 'aes128gcm';
        return 'aesgcm';
    };

    const storeSubscription = async (subscription) => {
        const payload = subscription.toJSON();
        await window.axios.post('/api/push-subscriptions', {
            endpoint: payload.endpoint,
            keys: payload.keys,
            content_encoding: contentEncoding(),
        });
    };

    const deleteSubscription = async (subscription) => {
        await window.axios.delete('/api/push-subscriptions', {
            data: { endpoint: subscription.endpoint },
        });
    };

    const refreshState = async () => {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (Notification.permission === 'denied') {
            setButtonState('Alerts blocked', true);
            return;
        }
        setButtonState(subscription ? 'Alerts on' : 'Enable alerts');
    };

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                setButtonState('Saving...', true);
                const registration = await navigator.serviceWorker.ready;
                const existing = await registration.pushManager.getSubscription();
                await ensureAudioContext().catch(() => null);

                if (existing) {
                    await deleteSubscription(existing);
                    await existing.unsubscribe();
                    setButtonState('Enable alerts');
                    return;
                }

                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    setButtonState(permission === 'denied' ? 'Alerts blocked' : 'Enable alerts', permission === 'denied');
                    return;
                }

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(key),
                });

                await storeSubscription(subscription);
                setButtonState('Alerts on');
            } catch (error) {
                console.error('Push notification setup failed:', error);
                setButtonState('Enable alerts');
            }
        });
    });

    toneButtons.forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => {
            const select = document.querySelector('[data-push-tone-select]');
            const tone = select instanceof HTMLSelectElement ? select.value : button.dataset.pushTonePreview;
            playTone(tone).catch((error) => console.error('Push notification tone preview failed:', error));
        });
    });

    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type !== 'life:push-tone') return;
        playTone(event.data.tone).catch(() => {});
    });

    window.addEventListener('load', () => {
        navigator.serviceWorker.ready.then(refreshState).catch(() => {});
    });
};

const initSupabase = () => {
    const url = import.meta.env.VITE_SUPABASE_URL;
    const key = import.meta.env.VITE_SUPABASE_ANON_KEY;
    if (!url || !key) return;
    try {
        window.supabase = createClient(url, key);
    } catch (error) {
        console.error('Supabase initialization failed:', error);
    }
};

const initTransportRealtime = () => {
    const root = document.querySelector('[data-transport-realtime]');
    if (!root) return;

    const echo = typeof window.getTransportEcho === 'function' ? window.getTransportEcho() : window.Echo;
    if (!echo) return;

    const channel = root.getAttribute('data-channel');
    if (!channel) return;

    const statusTarget = document.querySelector('[data-transport-status]');
    const noticeTarget = document.querySelector('[data-transport-notice]');

    try {
        echo.private(channel)
            .listen('.transport.request.offered', (event) => {
                if (noticeTarget) {
                    noticeTarget.textContent = `New request ${event.request_number} is available. Refresh if it does not appear in the list.`;
                }
            })
            .listen('.transport.request.accepted', (event) => {
                if (statusTarget && event.status) {
                    statusTarget.textContent = event.status.replaceAll('_', ' ');
                }
                if (noticeTarget) {
                    noticeTarget.textContent = event.driver_name
                        ? `${event.driver_name} accepted this request.`
                        : 'A driver accepted this request.';
                }
            })
            .listen('.transport.request.status', (event) => {
                if (statusTarget && event.status) {
                    statusTarget.textContent = event.status.replaceAll('_', ' ');
                }
                if (noticeTarget && event.note) {
                    noticeTarget.textContent = event.note;
                }
            });
    } catch (error) {
        console.error('Transport realtime initialization failed:', error);
    }
};

const initLocaleSwitchLoading = () => {
    const overlay = document.querySelector('[data-locale-loading]');
    const text = document.querySelector('[data-locale-loading-text]');
    const forms = Array.from(document.querySelectorAll('[data-locale-switch-form]'));

    if (!overlay || !forms.length) return;

    forms.forEach((form) => {
        form.addEventListener('submit', () => {
            const localeName = form.getAttribute('data-locale-name') || 'language';
            if (text) text.textContent = `Loading ${localeName}...`;
            overlay.hidden = false;
            document.documentElement.classList.add('locale-is-loading');

            form.querySelectorAll('[data-locale-switch-button]').forEach((button) => {
                button.setAttribute('aria-busy', 'true');
            });
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initReveal();
    initPublicNavigation();
    initSmoothScroll();
    initSectionHighlighting();
    initServiceWorker();
    initPushNotifications();
    initSupabase();
    initTransportRealtime();
    initLocaleSwitchLoading();
});
