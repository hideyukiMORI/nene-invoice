import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

/**
 * The @smoke tag marks the one spec that must pass before anything else is
 * worth running: boot the SPA → sign in → land on the authenticated shell →
 * move to another route without losing the session.
 *
 * It is HERMETIC — every API call is stubbed via `page.route` (see
 * helpers/auth.ts), so it needs no backend, no DB and no Docker, and it never
 * touches a deployed environment. `npm run e2e:smoke` runs this spec alone.
 *
 * Keep it thin. Feature behaviour belongs in the per-feature specs; what this
 * one guards is the wiring that makes all of them possible — bundle boots,
 * login form posts, token reaches the fail-closed auth gate, the shell renders,
 * and SPA navigation keeps the in-memory token alive.
 */
test('@smoke sign in and reach the authenticated shell', async ({ page }) => {
  // The clients list is the first route we navigate to; stub it before the nav
  // click so the assertion cannot race the request.
  await page.route('**/admin/clients*', (route) => route.fulfill(json({ items: [], total: 0 })))

  await login(page)

  // Landed on the post-login dashboard.
  await expect(page.getByRole('heading', { name: 'ダッシュボード' })).toBeVisible()

  // SPA navigation keeps the session (the token is in memory — a full reload
  // would clear it, so this also guards against an accidental hard navigation).
  await page.getByRole('link', { name: '取引先' }).click()
  await expect(page.getByRole('heading', { name: '取引先' })).toBeVisible()
})
