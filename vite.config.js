import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    lib: {
      entry: './src/admin.js',
      name: 'WincAdmin',
      formats: ['iife'],
      fileName: () => 'admin.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'admin.[ext]',
      },
    },
    outDir: 'dist',
    emptyOutDir: true,
  },
})
