import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Educativo';

// Root-level pages (e.g. Welcome.vue) live in resources/js/Pages.
const rootPages = import.meta.glob('./Pages/**/*.vue');

// Module-owned pages live in each module's own Resources/js/Pages directory
// and are resolved via the "ModuleName::PageName" naming convention.
const modulePages = import.meta.glob('../../Modules/*/Resources/js/Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        if (name.includes('::')) {
            const [moduleName, pageName] = name.split('::');

            return resolvePageComponent(
                `../../Modules/${moduleName}/Resources/js/Pages/${pageName}.vue`,
                modulePages,
            );
        }

        return resolvePageComponent(`./Pages/${name}.vue`, rootPages);
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(ZiggyVue)
            .mount(el);
    },
});
