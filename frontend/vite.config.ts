import path from 'node:path'
import { fileURLToPath } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

const dirname = path.dirname(fileURLToPath(import.meta.url))

// The PHP API runs same-origin in production (Tier A). In dev, Vite serves the
// SPA and proxies API paths to the running PHP app. Override the target with
// VITE_API_TARGET when the app listens elsewhere (e.g. the php -S dev port).
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, dirname, 'VITE_')
  const target = env['VITE_API_TARGET'] ?? 'http://127.0.0.1:8080'

  return {
    plugins: [react(), tailwindcss()],
    // Relative asset paths so the bundle can be served from a sub-path
    // (production same-origin wiring under public_html/ is a Tier A follow-up).
    base: './',
    resolve: {
      alias: {
        '@': path.resolve(dirname, './src'),
        '@tests': path.resolve(dirname, './tests'),
      },
    },
    build: {
      outDir: path.resolve(dirname, '../public_html/admin'),
      emptyOutDir: true,
    },
    server: {
      proxy: {
        '/auth': { target, changeOrigin: true },
        '/admin': { target, changeOrigin: true },
        '/health': { target, changeOrigin: true },
      },
    },
  }
})
