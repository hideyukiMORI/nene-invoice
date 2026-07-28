import { expect, test } from '@playwright/test'
import { json, problem } from './helpers/auth'

/**
 * Sign-in is the only feature reachable without an existing session, so it needs
 * no login helper. Covers client-side validation boundaries and the server-side
 * auth-failure path.
 */
test.describe('Sign in', () => {
  test('shows validation messages when submitting an empty form', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('button', { name: 'ログイン' }).click()

    await expect(page.getByText('有効なメールアドレスを入力してください。')).toBeVisible()
    await expect(page.getByText('パスワードを入力してください。')).toBeVisible()
  })

  test('rejects a malformed email address', async ({ page }) => {
    await page.goto('/')
    await page.locator('#email').fill('not-an-email')
    await page.locator('#password').fill('secret')
    await page.getByRole('button', { name: 'ログイン' }).click()

    await expect(page.getByText('有効なメールアドレスを入力してください。')).toBeVisible()
  })

  test('requires a password even with a valid email', async ({ page }) => {
    await page.goto('/')
    await page.locator('#email').fill('admin@example.com')
    await page.getByRole('button', { name: 'ログイン' }).click()

    await expect(page.getByText('パスワードを入力してください。')).toBeVisible()
  })

  test('surfaces an error on invalid credentials', async ({ page }) => {
    await page.route('**/auth/login', (route) => route.fulfill(problem('invalid-credentials', 401)))

    await page.goto('/')
    await page.locator('#email').fill('admin@example.com')
    await page.locator('#password').fill('wrong-password')
    await page.getByRole('button', { name: 'ログイン' }).click()

    await expect(page.getByText('メールアドレスまたはパスワードが正しくありません。')).toBeVisible()
  })

  test('signs in and reveals the authenticated app shell', async ({ page }) => {
    await page.route('**/auth/login', (route) => route.fulfill(json({ token: 'e2e-token' })))
    await page.route('**/admin/dashboard', (route) =>
      route.fulfill(
        json({ unpaid_count: 0, overdue_count: 0, outstanding_total_cents: 0, recent_unpaid: [] }),
      ),
    )
    await page.route('**/admin/me', (route) =>
      route.fulfill(json({ id: 1, email: 'admin@example.com', role: 'admin', organization_id: 1 })),
    )

    await page.goto('/')
    await page.locator('#email').fill('admin@example.com')
    await page.locator('#password').fill('correct-horse')
    await page.getByRole('button', { name: 'ログイン' }).click()

    await expect(page.getByRole('link', { name: '取引先' })).toBeVisible()
  })
})
