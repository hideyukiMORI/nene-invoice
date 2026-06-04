import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const CLIENT = {
  id: 5,
  organization_id: 1,
  name: '得意先ABC',
  contact_name: null,
  email: null,
  registration_number: null,
}
const INVOICE = {
  id: 7,
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

/** Reaches /invoices/new through login → invoices list → "請求書を作成". */
test.describe('Create invoice', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/clients*', (route) =>
      route.fulfill(json({ items: [CLIENT], total: 1, limit: 100, offset: 0 })),
    )
    await page.route('**/admin/invoices*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [], total: 0, limit: 20, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()
    await page.getByRole('link', { name: '請求書を作成' }).click()
    await expect(page.getByRole('heading', { name: '請求書の作成' })).toBeVisible()
  })

  test('validates a missing client and an empty line item', async ({ page }) => {
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page.getByText('入力内容に誤りがあります。').first()).toBeVisible()
    await expect(page).toHaveURL(/\/invoices\/new$/)
  })

  test('appends an extra line-item row', async ({ page }) => {
    await expect(page.locator('#line-1-description')).toHaveCount(0)
    await page.getByRole('button', { name: '明細を追加' }).click()
    await expect(page.locator('#line-1-description')).toBeVisible()
  })

  test('creates an invoice and navigates to its detail', async ({ page }) => {
    await page.route('**/admin/invoices*', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(json(INVOICE, 201))
      } else {
        route.fulfill(json({ items: [], total: 0, limit: 20, offset: 0 }))
      }
    })
    await page.route('**/admin/invoices/7', (route) => route.fulfill(json(INVOICE)))
    await page.route('**/admin/invoices/7/payments', (route) =>
      route.fulfill(json({ items: [], total_paid_cents: 0 })),
    )

    await page.locator('#client_id').selectOption('5')
    await page.locator('#line-0-description').fill('設計作業')
    await page.locator('#line-0-unit').fill('100000')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page).toHaveURL(/\/invoices\/7$/)
  })
})
