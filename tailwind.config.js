import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const withOpacity = (variable) => {
    return ({ opacityValue } = {}) => {
        if (opacityValue === undefined) {
            return `rgb(var(${variable}))`;
        }

        return `rgb(var(${variable}) / ${opacityValue})`;
    };
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    darkMode: ['class', '[data-theme="dark"]'],

    theme: {
        container: {
            center: true,
            padding: {
                DEFAULT: '1rem',
                sm: '1.5rem',
                lg: '2rem',
            },
        },
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                canvas: withOpacity('--canvas-rgb'),
                surface: withOpacity('--surface-rgb'),
                elevated: withOpacity('--elevated-rgb'),
                border: withOpacity('--border-rgb'),
                text: withOpacity('--text-rgb'),
                muted: withOpacity('--muted-rgb'),
                brand: {
                    DEFAULT: withOpacity('--brand-rgb'),
                    strong: withOpacity('--brand-strong-rgb'),
                    subtle: withOpacity('--brand-subtle-rgb'),
                },
                accent: {
                    DEFAULT: withOpacity('--accent-rgb'),
                    strong: withOpacity('--accent-strong-rgb'),
                },
            },
            boxShadow: {
                'soft-lg': '0 20px 48px rgba(2, 6, 23, 0.10)',
                'soft-md': '0 14px 34px rgba(2, 6, 23, 0.08)',
                'soft-sm': '0 10px 24px rgba(2, 6, 23, 0.06)',
            },
            borderRadius: {
                '4xl': '2rem',
            },
        },
    },

    plugins: [forms],
};
