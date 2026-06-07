import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { readFileSync } from 'node:fs';

const { version } = JSON.parse(readFileSync('./package.json', 'utf-8'));

export default defineConfig({
    define: {
        // Wird zur Build-Zeit injiziert; passt zum git-Tag v{version}.
        __APP_VERSION__: JSON.stringify(version),
        __GITHUB_REPO_URL__: JSON.stringify('https://github.com/mariokehl/gymportal-io'),
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue()
    ],
});
