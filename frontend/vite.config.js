import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// The backend started with `symfony serve` (or `php -S 127.0.0.1:8000 -t public`) from backend/.
const backend = 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    // The app and the API share one origin, as they do in production behind nginx:
    // the session cookie works without any CORS setup.
    proxy: {
      '/api': backend,
      '/health': backend,
    },
  },
  build: {
    // All the styles end up in one single CSS file.
    cssCodeSplit: false,
  },
})
