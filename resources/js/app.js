import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";

const appName =
    window.document.getElementsByTagName("title")[0]?.innerText || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        console.log("Resolving component:", name); // Debug
        const pages = import.meta.glob("./Pages/**/*.vue", { eager: true });
        console.log("Available pages:", Object.keys(pages)); // Debug
        const component = pages[`./Pages/${name}.vue`];
        console.log("Found component:", component); // Debug
        return component;
    },
    setup({ el, App, props, plugin }) {
        console.log("Setting up Inertia app:", { el, App, props }); // Debug
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
