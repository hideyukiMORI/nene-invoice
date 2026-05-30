import { defineConfig, devices } from '@playwright/test'

const PORT = 5180

/**
 * Single-feature browser tests. The app talks to the API over relative paths,
 * which each spec stubs via `page.route` — no live backend is needed, so the
 * tests are deterministic and focus on UI behaviour (validation, boundaries,
 * navigation). Auth is in-memory and lost on reload, so specs log in and then
 * navigate within the SPA (see e2e/helpers/auth.ts).
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? 'list' : 'html',
  // The dev server compiles routes on first navigation, so the very first
  // login / route transition can be slow. Generous per-test and assertion
  // timeouts absorb that cold-compile cost and keep the suite non-flaky.
  timeout: 60_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL: `http://localhost:${String(PORT)}`,
    // The app picks its locale from navigator.language; pin it to Japanese (the
    // primary locale) so the specs can assert on the ja message catalog.
    locale: 'ja-JP',
    trace: 'on-first-retry',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: `npx vite --port ${String(PORT)} --strictPort`,
    url: `http://localhost:${String(PORT)}`,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
})
