import { defineConfig } from 'vite';

export default defineConfig({
    define: { 'process.env.NODE_ENV': JSON.stringify('production') },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        lib: {
            entry: 'resources/ts/RatingWidget.tsx',
            formats: ['es'],
            fileName: () => 'rating.js',
            cssFileName: 'rating',
        },
    },
});
