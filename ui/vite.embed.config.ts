import { resolve } from 'node:path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: 'dist/embed',
    emptyOutDir: true,
    lib: {
      entry: resolve(__dirname, 'src/embed.tsx'),
      name: 'AitumalowEditor',
      formats: ['iife'],
      fileName: () => 'aitumalow-editor.js',
      cssFileName: 'aitumalow-editor',
    },
  },
})
