import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const EMPTY_LIST = { items: [], total: 0, limit: 20, offset: 0 }

/**
 * Mobile bottom tab bar (final design spec). It is `display:none` on desktop, so
 * we log in at the default width, then shrink the viewport to reveal it.
 */
test.describe('Mobile bottom navigation', () => {
  test('navigates via a tab and opens the drawer from 「メニュー」', async ({ page }) => {
    await page.route('**/admin/clients**', (r) => r.fulfill(json(EMPTY_LIST)))
    await login(page)

    await page.setViewportSize({ width: 390, height: 844 })

    const bar = page.locator('.bottom-nav')
    await expect(bar).toBeVisible()

    // A tab navigates within the SPA.
    await bar.getByText('取引先').click()
    await expect(page).toHaveURL(/\/clients$/)
    await expect(page.getByRole('heading', { name: '取引先一覧' })).toBeVisible()

    // The 「メニュー」 tab opens the off-canvas drawer holding the secondary items.
    await bar.getByRole('button').click()
    await expect(page.locator('.app.nav-open')).toBeVisible()
    await expect(page.locator('.side').getByRole('link', { name: 'ユーザー' })).toBeVisible()
  })
})
