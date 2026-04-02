import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        chunkSizeWarningLimit: 1600,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('@photo-sphere-viewer') || id.includes('three')) {
                            return 'sphere-viewer';
                        }
                        if (id.includes('@vueup') || id.includes('quill')) {
                            return 'quill';
                        }
                        if (id.includes('@heroicons')) {
                            return 'icons';
                        }
                        if (id.includes('vue') || id.includes('@inertiajs')) {
                            return 'framework';
                        }
                        return 'vendor';
                    }
                }
            }
        }
    }
});
