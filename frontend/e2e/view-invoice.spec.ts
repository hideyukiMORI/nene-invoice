import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const ISSUED_INVOICE = {
  id: 1,
  organization_id: 1,
  client_id: 5,
  status: 'issued',
  invoice_number: 'INV-2026-001',
  is_overdue: false,
  is_qualified_invoice: false,
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  issued_at: '2026-05-01',
  line_items: [],
}

/** Reaches /invoices/1 through login → invoices list → the invoice-number link. */
test.describe('View invoice', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/invoices*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [ISSUED_INVOICE], total: 1, limit: 20, offset: 0 }))
      } else {
        route.fallback()
      }
    })
    await page.route('**/admin/invoices/1', (route) => route.fulfill(json(ISSUED_INVOICE)))
    await page.route('**/admin/invoices/1/payments', (route) =>
      route.fulfill(json({ items: [], total_paid_cents: 0 })),
    )

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()
    await page.getByRole('link', { name: 'INV-2026-001' }).click()
    await expect(page.getByRole('button', { name: 'クライアント向けリンクを生成' })).toBeVisible()
  })

  test('generates a shareable download link and shows the copy action', async ({ page }) => {
    await page.route('**/admin/invoices/1/download-token', (route) =>
      route.fulfill(
        json({ url: '/invoices/download/abc123', expires_at: '2026-06-06 12:00:00' }, 201),
      ),
    )

    await page.getByRole('button', { name: 'クライアント向けリンクを生成' }).click()

    await expect(page.getByText('/invoices/download/abc123', { exact: false })).toBeVisible()
    await expect(page.getByRole('button', { name: 'コピー' })).toBeVisible()
  })

  test('shows an error when link generation fails', async ({ page }) => {
    await page.route('**/admin/invoices/1/download-token', (route) =>
      route.fulfill({ status: 500, contentType: 'application/json', body: '{}' }),
    )

    await page.getByRole('button', { name: 'クライアント向けリンクを生成' }).click()

    await expect(page.getByText('リンクの生成に失敗しました。')).toBeVisible()
  })
})
