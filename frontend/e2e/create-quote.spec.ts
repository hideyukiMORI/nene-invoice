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
const SUGGESTION = {
  description: 'コンサルティング（10時間）',
  unit_price_cents: 30000,
  tax_rate_bps: 1000,
  usage_count: 4,
  source: 'history',
}

/** Reaches /quotes/new through login → quotes list → "見積書を作成". */
test.describe('Create quote', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/clients*', (route) =>
      route.fulfill(json({ items: [CLIENT], total: 1, limit: 100, offset: 0 })),
    )
    await page.route('**/admin/line-items/suggestions*', (route) =>
      route.fulfill(json({ items: [SUGGESTION] })),
    )
    await page.route('**/admin/quotes*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [], total: 0, limit: 20, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '見積書', exact: true }).click()
    await page.getByRole('link', { name: '見積書を作成' }).click()
    await expect(page.getByRole('heading', { name: '見積書を作成' })).toBeVisible()
  })

  test('validates a missing client and an empty line item', async ({ page }) => {
    await page.getByRole('button', { name: '作成する' }).click()

    // The "client required" + "line description required" errors share copy.
    await expect(page.getByText('入力内容に誤りがあります。').first()).toBeVisible()
    await expect(page).toHaveURL(/\/quotes\/new$/)
  })

  test('appends an extra line-item row', async ({ page }) => {
    await expect(page.locator('#line-1-description')).toHaveCount(0)
    await page.getByRole('button', { name: '行を追加' }).click()
    await expect(page.locator('#line-1-description')).toBeVisible()
  })

  test('creates a quote and navigates to its detail', async ({ page }) => {
    await page.route('**/admin/quotes*', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(json(QUOTE, 201))
      } else {
        route.fulfill(json({ items: [], total: 0, limit: 20, offset: 0 }))
      }
    })
    await page.route('**/admin/quotes/1', (route) => route.fulfill(json(QUOTE)))

    await page.locator('#client_id').fill('得意先')
    await page.getByRole('option', { name: '得意先ABC' }).click()
    await page.locator('#line-0-description').fill('設計作業')
    await page.locator('#line-0-unit').fill('100000')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page).toHaveURL(/\/quotes\/1$/)
  })

  test('picks a line-item suggestion to fill the description and unit price', async ({ page }) => {
    await page.locator('#line-0-description').fill('コンサル')
    await page.getByRole('option', { name: /コンサルティング（10時間）/ }).click()

    await expect(page.locator('#line-0-description')).toHaveValue('コンサルティング（10時間）')
    await expect(page.locator('#line-0-unit')).toHaveValue('30000')
  })
})
