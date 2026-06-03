import { defineConfig } from 'vitest/config';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        // Mirror the laravel-vite-plugin `@` alias so unit tests can import
        // the same modules the app does.
        include: ['resources/js/**/*.{test,spec}.{js,ts}'],
        // Run in a US timezone so the date-display regression tests actually
        // exercise the negative-offset case a German user never sees locally.
        env: {
            TZ: 'America/Los_Angeles',
        },
    },
});
