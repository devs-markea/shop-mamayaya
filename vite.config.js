import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // One global stylesheet and one global script. Each screen's own script is
            // added here as a separate entry (resources/js/pages/*.ts) as it gets
            // migrated, so every page loads only its own JavaScript.
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                'resources/js/pages/menu.ts',
                'resources/js/pages/product.ts',
                'resources/js/pages/cart.ts',
                'resources/js/pages/delivery.ts',
                'resources/js/pages/payment.ts',
                'resources/js/pages/payment-transfer.ts',
                'resources/js/pages/payment-cash.ts',
                'resources/js/pages/confirmation.ts',
            ],

            // Files the views render with Vite::asset(). Without this they never make it
            // into the manifest and the build doesn't hash them. The Inter file is the
            // one the layout preloads (the same one fonts.css points to).
            assets: [
                'resources/images/**',
                'node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2',
            ],

            refresh: true,
        }),
    ],

    // Bootstrap's JS ships as UMD, so Vite has to pre-bundle it. If it discovers it on
    // the fly (the first time a page imports it), it re-optimizes mid-session and the
    // modules already served start answering 504, taking down the whole page's JS
    // without a trace in the console. Declared here, they get bundled at startup.
    optimizeDeps: {
        include: ['bootstrap/js/dist/dropdown.js'],
    },
});
