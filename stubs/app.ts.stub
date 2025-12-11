import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { notify } from '@laravilt/notifications/app';
import LaraviltForms from '@laravilt/forms/app';
import LaraviltTables from '@laravilt/tables/app';
import LaraviltWidgets from '@laravilt/widgets/app';

// Make notify globally available
(window as any).notify = notify;

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        // Try to resolve from app pages first
        const appPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
        const panelPages = import.meta.glob<DefineComponent>('../../vendor/laravilt/panel/resources/js/pages/**/*.vue');
        const authPages = import.meta.glob<DefineComponent>('../../vendor/laravilt/auth/resources/js/Pages/**/*.vue');
        const aiPages = import.meta.glob<DefineComponent>('../../vendor/laravilt/ai/resources/js/Pages/**/*.vue');

        // Handle laravilt-auth/ prefix
        if (name.startsWith('laravilt-auth/')) {
            const authPageName = name.replace('laravilt-auth/', '');
            return await resolvePageComponent(
                `../../vendor/laravilt/auth/resources/js/Pages/${authPageName}.vue`,
                authPages,
            );
        }

        try {
            return await resolvePageComponent(
                `./pages/${name}.vue`,
                appPages,
            );
        } catch (e) {
            // If not found in app pages, try panel pages
            try {
                return await resolvePageComponent(
                    `../../vendor/laravilt/panel/resources/js/pages/${name}.vue`,
                    panelPages,
                );
            } catch (e2) {
               try {
                   // If not found in panel pages, try auth pages
                   return await resolvePageComponent(
                       `../../vendor/laravilt/auth/resources/js/Pages/${name}.vue`,
                       authPages,
                   );
               } catch (e3) {
                   // If not found in auth pages, try ai pages
                   return await resolvePageComponent(
                       `../../vendor/laravilt/ai/resources/js/Pages/${name}.vue`,
                       aiPages,
                   );
               }
            }
        }
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(LaraviltForms)
            .use(LaraviltTables)
            .use(LaraviltWidgets)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
