import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const QUOTE = {
  id: 1,
  organization_id: 1,
  client_id: 5,
  quote_number: 'EST-2026-001',
  status: 'draft',
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  line_items: [],
}
const INVOICE = {
  id: 10,
  organization_id: 1,
  client_id: 5,
  status: 'draft',
  invoice_number: null,
  is_overdue: false,
  is_qualified_invoice: false,
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  line_items: [],
}

/** Reaches /quotes/1 through login → quotes list → the quote-number link. */
test.describe('View quote', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/quotes*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [QUOTE], total: 1, limit: 20, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '見積書', exact: true }).click()
    await expect(page.getByRole('link', { name: 'EST-2026-001' })).toBeVisible()
  })

  test('offers "send" for a draft quote but not conversion', async ({ page }) => {
    await page.route('**/admin/quotes/1', (route) => route.fulfill(json(QUOTE)))
    await page.getByRole('link', { name: 'EST-2026-001' }).click()

    await expect(page.getByRole('button', { name: '送付する' })).toBeVisible()
    await expect(page.getByRole('button', { name: '請求書に変換する' })).toHaveCount(0)
  })

  test('offers conversion only once the quote is accepted', async ({ page }) => {
    await page.route('**/admin/quotes/1', (route) =>
      route.fulfill(json({ ...QUOTE, status: 'accepted' })),
    )
    await page.getByRole('link', { name: 'EST-2026-001' }).click()

    await expect(page.getByRole('button', { name: '請求書に変換する' })).toBeVisible()
    await expect(page.getByRole('button', { name: '送付する' })).toHaveCount(0)
  })

  test('converts an accepted quote into an invoice', async ({ page }) => {
    await page.route('**/admin/quotes/1', (route) =>
      route.fulfill(json({ ...QUOTE, status: 'accepted' })),
    )
    await page.route('**/admin/quotes/1/convert', (route) => route.fulfill(json(INVOICE, 201)))
    await page.route('**/admin/invoices/10', (route) => route.fulfill(json(INVOICE)))
    await page.route('**/admin/invoices/10/payments', (route) =>
      route.fulfill(json({ items: [], total_paid_cents: 0 })),
    )

    await page.getByRole('link', { name: 'EST-2026-001' }).click()
    await page.getByRole('button', { name: '請求書に変換する' }).click()

    await expect(page).toHaveURL(/\/invoices\/10$/)
  })
})
