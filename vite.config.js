import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/invoice.css',
                'resources/css/invoice-draft.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
