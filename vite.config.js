import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    base: './',
    publicDir: false,
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./assets/vue', import.meta.url)),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['color-functions', 'global-builtin', 'import', 'mixed-decls'],
            },
        },
    },
    plugins: [vue()],
    build: {
        outDir: 'assets/build',
        emptyOutDir: true,
        cssCodeSplit: false,
        manifest: true,
        rollupOptions: {
            input: fileURLToPath(new URL('./assets/vue/app.js', import.meta.url)),
            output: {
                entryFileNames: 'app-[hash].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.names?.[0] || assetInfo.name || '';
                    return name.endsWith('.css') ? 'app-[hash][extname]' : '[name][extname]';
                },
            },
        },
    },
});
