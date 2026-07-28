import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

test.describe('Language switcher', () => {
  test('switches the UI language and persists the choice', async ({ page }) => {
    await login(page)

    // Default is Japanese: the sidebar shows 取引先.
    const side = page.locator('.side')
    await expect(side.getByRole('link', { name: '取引先' })).toBeVisible()

    // Switch to English from the sidebar account menu.
    await side.getByRole('button', { name: 'English' }).click()
    await expect(side.getByRole('link', { name: 'Clients' })).toBeVisible()
    await expect(side.getByRole('link', { name: '取引先' })).toHaveCount(0)

    // The choice persists across a reload (localStorage). A reload clears the
    // in-memory token, so we land on the login screen — now in English.
    await page.reload()
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible()
  })
})
