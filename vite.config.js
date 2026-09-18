import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Fashion template (base views)
                'resources/css/app.css',
                'resources/js/app.js',
                // Indian Grocery template
                'resources/templates/grocery/css/app.css',
                'resources/templates/grocery/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
