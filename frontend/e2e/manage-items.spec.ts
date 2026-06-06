import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const ITEM = {
  id: 9,
  organization_id: 1,
  description: '保守サポート（月額）',
  default_unit_price_cents: 50000,
  default_tax_rate_bps: 1000,
}

/** Reaches /items through login → sidebar "品目". */
test.describe('Manage items', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/items*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [ITEM], total: 1, limit: 100, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '品目', exact: true }).click()
    await expect(page.getByRole('heading', { name: '品目一覧' })).toBeVisible()
  })

  test('lists items with price and tax rate', async ({ page }) => {
    await expect(page.getByText('保守サポート（月額）')).toBeVisible()
    await expect(page.getByText('¥50,000')).toBeVisible()
    await expect(page.getByText('10%')).toBeVisible()
  })

  test('creates an item and returns to the list', async ({ page }) => {
    await page.getByRole('link', { name: '品目を作成' }).click()
    await expect(page.getByRole('heading', { name: '品目の作成' })).toBeVisible()

    await page.route('**/admin/items*', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(json(ITEM, 201))
      } else {
        route.fulfill(json({ items: [ITEM], total: 1, limit: 100, offset: 0 }))
      }
    })

    await page.locator('#description').fill('保守サポート（月額）')
    await page.locator('#default_unit_price_cents').fill('50000')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page).toHaveURL(/\/items$/)
    await expect(page.getByRole('heading', { name: '品目一覧' })).toBeVisible()
  })

  test('requires a description', async ({ page }) => {
    await page.getByRole('link', { name: '品目を作成' }).click()
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page.getByText('品目名を入力してください。')).toBeVisible()
    await expect(page).toHaveURL(/\/items\/new$/)
  })

  test('shows an error when the server rejects creation', async ({ page }) => {
    await page.getByRole('link', { name: '品目を作成' }).click()

    await page.route('**/admin/items*', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(problem('validation-failed', 422))
      } else {
        route.fulfill(json({ items: [ITEM], total: 1, limit: 100, offset: 0 }))
      }
    })

    await page.locator('#description').fill('x')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page.getByText('品目を作成できませんでした。', { exact: false })).toBeVisible()
    await expect(page).toHaveURL(/\/items\/new$/)
  })

  test('deletes an item after confirmation', async ({ page }) => {
    await page.route('**/admin/items/9', (route) => route.fulfill({ status: 204, body: '' }))

    await page.getByRole('button', { name: '削除' }).click()
    await page.getByRole('button', { name: '削除する' }).click()

    await expect(page.getByRole('dialog')).toHaveCount(0)
  })
})
