import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const TOKEN = {
  id: 5,
  subject: 'service:clear',
  label: 'NeNe Clear',
  scopes: ['read:invoices', 'write:payments'],
  created_by: 1,
  created_at: '2026-06-09 00:00:00',
  expires_at: '2026-07-09 00:00:00',
  revoked_at: null,
  status: 'active',
}

test.describe('Service tokens', () => {
  test('lists issued tokens after login', async ({ page }) => {
    await page.route('**/admin/service-tokens*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [TOKEN], total: 1, limit: 100, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: 'サービストークン' }).click()

    await expect(page.getByRole('heading', { name: 'サービストークン' })).toBeVisible()
    // exact: the label appears verbatim in the row; the subtitle also contains
    // "NeNe Clear", and the status badge "有効" is a substring of the "有効期限" header.
    await expect(page.getByText('NeNe Clear', { exact: true })).toBeVisible()
    await expect(page.getByText('有効', { exact: true })).toBeVisible()
  })

  test('issues a token and reveals its one-time value', async ({ page }) => {
    await page.route('**/admin/service-tokens*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 }))
      } else if (request.method() === 'POST') {
        route.fulfill(json({ ...TOKEN, token: 'signed.jwt.value' }, 201))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: 'サービストークン' }).click()

    await page.locator('#label').fill('NeNe Clear 本番')
    await page.getByRole('button', { name: '発行する' }).click()

    // The one-time token is revealed in a read-only field with a warning.
    await expect(page.locator('#issued-token')).toHaveValue('signed.jwt.value')
    await expect(
      page.getByText('このトークンは今回だけ表示されます。', { exact: false }),
    ).toBeVisible()
  })

  test('revokes a token after confirmation', async ({ page }) => {
    let revoked = false
    await page.route('**/admin/service-tokens/5', (route, request) => {
      if (request.method() === 'DELETE') {
        revoked = true
        route.fulfill({ status: 204, body: '' })
      } else {
        route.fallback()
      }
    })
    await page.route('**/admin/service-tokens*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [TOKEN], total: 1, limit: 100, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: 'サービストークン' }).click()

    await page.getByRole('button', { name: '失効', exact: true }).click()
    await page.getByRole('button', { name: '失効する' }).click()

    await expect.poll(() => revoked).toBe(true)
  })
})
