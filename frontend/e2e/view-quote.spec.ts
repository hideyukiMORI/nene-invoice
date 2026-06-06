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

  test('duplicates a quote into a prefilled create form', async ({ page }) => {
    await page.route('**/admin/quotes/1', (route) =>
      route.fulfill(
        json({
          ...QUOTE,
          client_id: 5,
          notes: '毎月の保守',
          line_items: [
            {
              description: '保守サポート',
              quantity: 1,
              unit_price_cents: 50000,
              tax_rate_bps: 1000,
              line_subtotal_cents: 50000,
            },
          ],
        }),
      ),
    )
    // The create form loads clients (for the picker) and line-item suggestions.
    await page.route('**/admin/clients*', (route) =>
      route.fulfill(
        json({
          items: [{ id: 5, organization_id: 1, name: '得意先ABC', name_kana: null }],
          total: 1,
          limit: 100,
          offset: 0,
        }),
      ),
    )
    await page.route('**/admin/line-items/suggestions*', (route) =>
      route.fulfill(json({ items: [] })),
    )

    await page.getByRole('link', { name: 'EST-2026-001' }).click()
    await page.getByRole('button', { name: 'この内容で複製' }).click()

    await expect(page).toHaveURL(/\/quotes\/new$/)
    await expect(page.locator('#line-0-description')).toHaveValue('保守サポート')
    await expect(page.locator('#line-0-unit')).toHaveValue('50000')
    await expect(page.locator('#client_id')).toHaveValue('得意先ABC')
  })

  test('triggers PDF download for a quote', async ({ page }) => {
    await page.route('**/admin/quotes/1', (route) => route.fulfill(json(QUOTE)))
    await page.route('**/admin/quotes/1/pdf', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/pdf',
        body: Buffer.from('%PDF-1.4 fake'),
      }),
    )

    await page.getByRole('link', { name: 'EST-2026-001' }).click()
    await expect(page.getByRole('button', { name: 'PDF をダウンロード' })).toBeVisible()

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'PDF をダウンロード' }).click(),
    ])

    expect(download.suggestedFilename()).toMatch(/EST-2026-001.*\.pdf/)
  })
})
