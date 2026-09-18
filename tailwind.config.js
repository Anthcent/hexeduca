import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './Modules/**/Resources/js/**/*.vue',
        './Modules/**/Resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Roboto', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: '#4285f4',
                blue: {
                    DEFAULT: '#0b57d0',
                    hover: '#0842a0',
                    light: '#c2e7ff',
                },
                red: {
                    DEFAULT: '#ea4335',
                    tint: '#fce8e6',
                },
                yellow: {
                    DEFAULT: '#fbbc04',
                    tint: '#fef7e0',
                },
                green: {
                    DEFAULT: '#34a853',
                    tint: '#e6f4ea',
                },
                canvas: '#f2f2f2',
                outline: {
                    DEFAULT: '#dadce0',
                    strong: '#bdc1c6',
                },
                ink: {
                    DEFAULT: '#1f1f1f',
                    muted: '#747775',
                    dark: '#131314',
                },
            },
            borderRadius: {
                g1: '8px',
                g2: '12px',
                g3: '16px',
                g4: '24px',
                pill: '999px',
            },
            spacing: {
                g1: '4px',
                g2: '8px',
                g3: '16px',
                g4: '24px',
                g5: '32px',
                g6: '48px',
                g7: '64px',
            },
            boxShadow: {
                e1: '0 1px 2px 0 rgba(31,31,31,0.15), 0 1px 3px 1px rgba(31,31,31,0.10)',
                e2: '0 1px 2px 0 rgba(31,31,31,0.20), 0 2px 6px 2px rgba(31,31,31,0.12)',
                e3: '0 4px 8px 3px rgba(31,31,31,0.12), 0 1px 3px rgba(31,31,31,0.20)',
                e4: '0 6px 10px 4px rgba(31,31,31,0.12), 0 2px 3px rgba(31,31,31,0.24)',
            },
        },
    },
    plugins: [forms],
};
