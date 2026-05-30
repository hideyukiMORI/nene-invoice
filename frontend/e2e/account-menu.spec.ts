import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

/** Sign-out clears the in-memory session and the auth gate falls back to login. */
test.describe('Account menu', () => {
  test('shows the signed-in email', async ({ page }) => {
    await login(page)

    await expect(page.getByText('admin@example.com', { exact: false })).toBeVisible()
  })

  test('signs out and returns to the login screen', async ({ page }) => {
    await login(page)

    await page.getByRole('button', { name: 'ログアウト' }).click()

    // The app shell is gone; the login form is shown again.
    await expect(page.locator('#email')).toBeVisible()
    await expect(page.getByRole('button', { name: 'ログイン' })).toBeVisible()
    await expect(page.getByRole('link', { name: '取引先' })).toHaveCount(0)
  })
})
