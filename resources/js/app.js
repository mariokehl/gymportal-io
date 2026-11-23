import { createApp, h } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import NotificationContainer from '@/Components/NotificationContainer.vue';
import { registerFlashToasts } from '@/composables/useFlashToasts';
import { applyScheme, watchSystemScheme } from '@/utils/colorScheme';

// Configuring Axios for Sanctum
import './bootstrap';

// Register the self-hosted fonts in the Vite manifest so app.blade.php can
// preload them via Vite::asset().
import.meta.glob('../fonts/*.woff2', { eager: false });

// Import Echo for WebSocket support
import './echo';

const appName = import.meta.env.VITE_APP_NAME || 'gymportal.io';

// Server messages (session flash) become toasts, once per response.
registerFlashToasts(router);

// Light/dark: app.blade.php resolves the first paint; afterwards follow the OS
// while the preference is "system", and pick up a changed preference after
// every visit (e.g. once the profile setting has been saved).
const colorSchemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
watchSystemScheme(document.documentElement, colorSchemeQuery);
router.on('navigate', (event) => {
    const preference = event.detail.page.props.auth?.user?.theme_preference;
    if (preference) {
        applyScheme(document.documentElement, preference, colorSchemeQuery.matches);
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // The toast container sits next to the page, so every layout shows toasts.
        return createApp({ render: () => [h(App, props), h(NotificationContainer)] })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
