import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/js/app.js'
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '~bootstrap': 'bootstrap',
            '~bootstrap-icons': 'bootstrap-icons',
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 todavia usa @import y funciones de color viejas
                // por dentro; sus avisos no los podemos arreglar desde aca.
                quietDeps: true,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
