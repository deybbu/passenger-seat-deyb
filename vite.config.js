import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/app-theme.css', 'resources/css/admin.css', 'resources/css/landing.css', 'resources/css/qr-order.css', 'resources/css/kitchen.css', 'resources/css/pos.css', 'resources/css/pos-register.css', 'resources/js/app.js', 'resources/js/app-theme.js', 'resources/js/admin.js', 'resources/js/landing-events.js', 'resources/js/qr-order.js', 'resources/js/kitchen.js', 'resources/js/pos.js', 'resources/js/pos-register.js', 'resources/js/pos-shell.js', 'resources/js/pos-table-qr.js', 'resources/js/inventory-scanner.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
