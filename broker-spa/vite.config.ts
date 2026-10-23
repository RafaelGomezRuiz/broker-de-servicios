import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    // La referencia de Scalar (~1 MB comprimido) es grande, pero va en su propio
    // chunk y solo se descarga al abrir esa pantalla.
    chunkSizeWarningLimit: 3500,
  },
  server: {
    // Debe coincidir con SPA_URL del broker-api (CORS y cookie de sesión).
    port: 5173,
    strictPort: true,
  },
})
