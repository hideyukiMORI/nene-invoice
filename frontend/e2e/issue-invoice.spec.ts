import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const DRAFT_INVOICE = {
  id: 1,
  organization_id: 1,
  client_id: 5,
  status: 'draft',
  invoice_number: null,
  is_overdue: false,
  is_qualified_invoice: true,
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  line_items: [],
}

/** Reaches a draft invoice's detail through login → invoices list → the row link. */
test.describe('Issue invoice', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/invoices*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [DRAFT_INVOICE], total: 1, limit: 20, offset: 0 }))
      } else {
        route.fallback()
      }
    })
    await page.route('**/admin/invoices/1', (route) => route.fulfill(json(DRAFT_INVOICE)))
    await page.route('**/admin/invoices/1/payments', (route) =>
      route.fulfill(json({ items: [], total_paid_cents: 0 })),
    )

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()
    await page.locator('a[href="/invoices/1"]').click()
    await expect(page.getByRole('button', { name: '発行する（適格請求書）' })).toBeVisible()
  })

  test('opens an irreversible-issue confirmation dialog', async ({ page }) => {
    await page.getByRole('button', { name: '発行する（適格請求書）' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText('請求書を発行しますか？')).toBeVisible()
  })

  test('surfaces an error when issuing an invoice without a registration number', async ({
    page,
  }) => {
    await page.route('**/admin/invoices/1/issue', (route) =>
      route.fulfill(problem('qualified-invoice-incomplete', 422)),
    )

    await page.getByRole('button', { name: '発行する（適格請求書）' }).click()
    await page.getByRole('dialog').getByRole('button', { name: '発行する（適格請求書）' }).click()

    await expect(
      page.getByText('発行できませんでした。会社情報の登録番号を確認してください。'),
    ).toBeVisible()
  })
})
