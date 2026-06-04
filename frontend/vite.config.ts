import path from 'node:path'
import { fileURLToPath } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

const dirname = path.dirname(fileURLToPath(import.meta.url))

// The PHP API runs same-origin in production (Tier A). In dev, Vite serves the
// SPA and proxies API paths to the running PHP app. Override the target with
// VITE_API_TARGET when the app listens elsewhere (e.g. the php -S dev port).
// NeNe Invoice fixed dev port: 8510 (php -S localhost:8510 -t public_html)
export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, dirname, 'VITE_')
  const target = env['VITE_API_TARGET'] ?? 'http://127.0.0.1:8510'

  return {
    plugins: [react(), tailwindcss()],
    // Relative asset paths for the production bundle so it can be served from a
    // sub-path (Tier A). The dev server needs an absolute base ('/') — a relative
    // base breaks dev import-URL normalization.
    base: command === 'build' ? './' : '/',
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
      port: 5185, // NeNe Invoice 固定: 5185
      proxy: {
        '/auth': { target, changeOrigin: true },
        '/admin': { target, changeOrigin: true },
        '/health': { target, changeOrigin: true },
        // Public, unauthenticated PDF download (client-facing token link). It
        // lives under the SPA's /invoices namespace, so it must be proxied to
        // the API explicitly — otherwise Vite serves the SPA shell and the link
        // bounces to the login screen. The prefix is narrow enough that SPA
        // routes (/invoices, /invoices/:id) are unaffected. Prod routes this via
        // .htaccess → index.php, so this is a dev-only concern.
        '/invoices/download': { target, changeOrigin: true },
      },
    },
  }
})
