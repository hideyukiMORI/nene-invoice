import path from 'node:path'
import { fileURLToPath } from 'node:url'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

const dirname = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(dirname, './src'),
      '@tests': path.resolve(dirname, './tests'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: false,
    setupFiles: ['./tests/setup/vitest.setup.ts'],
    include: ['src/**/*.test.ts', 'src/**/*.test.tsx', 'tests/**/*.test.ts', 'tests/**/*.test.tsx'],
    coverage: {
      provider: 'v8',
      // json-summary feeds the shrink-only ratchet (tools/coverage-ratchet.mjs);
      // text-summary is for humans, json for drill-down. Never write html into the repo.
      reporter: ['text-summary', 'json-summary', 'json'],
      reportsDirectory: './coverage',
      // Measure the application source only. Test/story/mock/setup files, type-only
      // barrels, generated code, and message catalogs are not units under test.
      include: ['src/**/*.{ts,tsx}'],
      exclude: [
        'src/**/*.test.{ts,tsx}',
        'src/**/*.stories.{ts,tsx}',
        'src/**/*.d.ts',
        'src/**/index.ts',
        'src/main.tsx',
        'src/**/__mocks__/**',
        'src/shared/api/schema.gen.ts',
        'src/shared/i18n/messages/**',
      ],
    },
  },
})
