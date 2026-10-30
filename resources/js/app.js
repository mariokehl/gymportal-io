import { createApp, h } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import NotificationContainer from '@/Components/NotificationContainer.vue';
import { registerFlashToasts } from '@/composables/useFlashToasts';

// Configuring Axios for Sanctum
import './bootstrap';

// Import Echo for WebSocket support
import './echo';

const appName = import.meta.env.VITE_APP_NAME || 'gymportal.io';

// Server messages (session flash) become toasts, once per response.
registerFlashToasts(router);

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
