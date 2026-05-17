<style>
    .filter-dropdown {
        margin-bottom: 1rem;
    }

    .filter-dropdown > summary {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 2.45rem;
        padding: 0.55rem 0.85rem;
        border: 1px solid rgba(148, 163, 184, 0.55);
        border-radius: 0.7rem;
        background: rgba(255, 255, 255, 0.92);
        color: #111827;
        font-weight: 700;
        font-size: 0.92rem;
        cursor: pointer;
        list-style: none;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }

    .filter-dropdown > summary::-webkit-details-marker {
        display: none;
    }

    .filter-dropdown > summary::after {
        content: "";
        width: 0.48rem;
        height: 0.48rem;
        border-right: 2px solid currentColor;
        border-bottom: 2px solid currentColor;
        transform: rotate(45deg) translateY(-0.1rem);
        transition: transform 160ms ease;
    }

    .filter-dropdown[open] > summary::after {
        transform: rotate(225deg) translateY(-0.1rem);
    }

    .filter-dropdown-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.35rem;
        height: 1.35rem;
        padding: 0 0.35rem;
        border-radius: 999px;
        background: #4f46e5;
        color: #fff;
        font-size: 0.75rem;
        line-height: 1;
    }

    .filter-dropdown-form {
        margin-top: 0.8rem;
    }

    html[data-theme="dark"] .filter-dropdown > summary {
        border-color: #334155;
        background: #0f172a;
        color: #e5eefb;
    }
</style>

<script>
    (() => {
        const ready = (callback) => {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback, { once: true });
                return;
            }

            callback();
        };

        const formMethod = (form) => String(form.getAttribute('method') || 'get').toLowerCase();

        const controlName = (control) => control.getAttribute('name');

        const visibleFilterControls = (form) => Array.from(form.elements || []).filter((control) => {
            const name = controlName(control);
            const type = String(control.getAttribute('type') || control.type || '').toLowerCase();

            return name && name !== 'page' && type !== 'hidden' && type !== 'submit' && type !== 'button';
        });

        const hasActiveQuery = (form, params) => visibleFilterControls(form).some((control) => params.has(controlName(control)));

        const activeControlCount = (form, params) => {
            const names = new Set();

            visibleFilterControls(form).forEach((control) => {
                const name = controlName(control);

                if (params.has(name)) {
                    names.add(name);
                }
            });

            return names.size;
        };

        const looksLikeFilterForm = (form) => {
            if (form.dataset.filterDropdown === 'false') {
                return false;
            }

            if (form.dataset.filterDropdown === 'true') {
                return true;
            }

            const formText = String(form.textContent || '').toLowerCase();
            const formClass = String(form.getAttribute('class') || '').toLowerCase();
            const controls = visibleFilterControls(form);
            const buttonText = Array.from(form.querySelectorAll('button, input[type="submit"]'))
                .map((button) => String(button.textContent || button.value || '').toLowerCase())
                .join(' ');
            const placeholderText = controls
                .map((control) => String(control.getAttribute('placeholder') || '').toLowerCase())
                .join(' ');

            return formText.includes('filter')
                || buttonText.includes('filter')
                || buttonText.includes('search')
                || buttonText.includes('lookup')
                || formClass.includes('search-form')
                || formClass.includes('filter')
                || placeholderText.includes('search');
        };

        const dropdownTitle = (form) => {
            if (form.dataset.filterTitle) {
                return form.dataset.filterTitle;
            }

            const text = String(form.textContent || '').toLowerCase();
            const placeholders = Array.from(form.querySelectorAll('input[placeholder], textarea[placeholder]'))
                .map((control) => String(control.getAttribute('placeholder') || '').toLowerCase())
                .join(' ');

            if (text.includes('timeline') || placeholders.includes('timeline')) {
                return 'Timeline filters';
            }

            if (text.includes('search') || placeholders.includes('search')) {
                return 'Search filters';
            }

            return 'Filters';
        };

        ready(() => {
            const params = new URLSearchParams(window.location.search);

            document.querySelectorAll('form').forEach((form) => {
                if (formMethod(form) !== 'get' || form.closest('.filter-dropdown') || ! looksLikeFilterForm(form)) {
                    return;
                }

                const details = document.createElement('details');
                details.className = 'filter-dropdown';
                details.dataset.filterDropdownRoot = 'true';
                details.open = hasActiveQuery(form, params);

                const summary = document.createElement('summary');
                const title = document.createElement('span');
                title.textContent = dropdownTitle(form);
                summary.appendChild(title);

                const count = activeControlCount(form, params);

                if (count > 0) {
                    const badge = document.createElement('span');
                    badge.className = 'filter-dropdown-count';
                    badge.textContent = String(count);
                    summary.appendChild(badge);
                }

                form.classList.add('filter-dropdown-form');
                form.parentNode.insertBefore(details, form);
                details.appendChild(summary);
                details.appendChild(form);
            });
        });
    })();
</script>
