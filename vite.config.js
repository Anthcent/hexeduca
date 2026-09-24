import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import ui from '@nuxt/ui/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // Nuxt UI also registers @tailwindcss/vite, so Tailwind must not be added again here.
        ui({
            router: 'inertia',
            // The dashboard layout owns the light/dark toggle (the "educativo-theme" key
            // in localStorage); Nuxt UI's color mode would follow the OS preference instead.
            colorMode: false,
            ui: {
                colors: {
                    primary: 'brand',
                    neutral: 'slate',
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
