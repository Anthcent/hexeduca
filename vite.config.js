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
            // Bundle the icons used by name (e.g. `icon="i-lucide-bell"`) and Nuxt UI's
            // own defaults, so they never fall back to fetching from api.iconify.design.
            // The scan only sees literal names: never build an icon name at runtime.
            icon: {
                clientBundle: {
                    scan: true,
                },
            },
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
