import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
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
            colors: {
                brand: {
                    50: '#edfdf6', 100: '#d4f8e8', 200: '#acf0d4', 300: '#76e2b9',
                    400: '#3ccb98', 500: '#18a879', 600: '#0f8561', 700: '#0b6b50',
                    800: '#0a5541', 900: '#094637', 950: '#04271f',
                },
                ink: '#0c1814',
                lime: '#b7e35b',
                amber: '#f0a63b',
                sky: '#4ba6c8',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Manrope', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                soft: '0 10px 30px rgba(7, 62, 45, 0.08)',
                lift: '0 22px 55px rgba(5, 45, 34, 0.16)',
                focus: '0 0 0 4px rgba(24, 168, 121, 0.18)',
            },
            borderRadius: { '2xl': '1.25rem', '3xl': '1.75rem' },
            keyframes: {
                'float-in': { '0%': { opacity: '0', transform: 'translateY(12px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                'pop-in': { '0%': { opacity: '0', transform: 'scale(.94)' }, '100%': { opacity: '1', transform: 'scale(1)' } },
                'progress-in': { '0%': { transform: 'scaleX(0)' }, '100%': { transform: 'scaleX(1)' } },
                'soft-pulse': { '0%,100%': { opacity: '.45' }, '50%': { opacity: '1' } },
            },
            animation: {
                'float-in': 'float-in .38s cubic-bezier(.2,.8,.2,1) both',
                'pop-in': 'pop-in .22s cubic-bezier(.2,.8,.2,1) both',
                'progress-in': 'progress-in .8s cubic-bezier(.2,.8,.2,1) both',
                'soft-pulse': 'soft-pulse 1.8s ease-in-out infinite',
            },
        },
    },
    plugins: [forms],
};
