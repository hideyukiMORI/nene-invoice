import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const TEMPLATE = {
  id: 9,
  organization_id: 1,
  name: '月次保守テンプレート',
  notes: '毎月の定期保守',
  line_items: [
    {
      id: 1,
      description: '保守サポート',
      quantity: 1,
      unit_price_cents: 50000,
      tax_rate_bps: 1000,
    },
  ],
}
const TEMPLATE_ROW = { ...TEMPLATE, line_items: [] }

/** Reaches /templates through login → sidebar "テンプレート". */
test.describe('Manage templates', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/templates*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [TEMPLATE_ROW], total: 1, limit: 50, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: 'テンプレート', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'テンプレート一覧' })).toBeVisible()
  })

  test('lists templates with name and notes', async ({ page }) => {
    await expect(page.getByText('月次保守テンプレート')).toBeVisible()
    await expect(page.getByText('毎月の定期保守')).toBeVisible()
  })

  test('creates a template and returns to the list', async ({ page }) => {
    await page.getByRole('link', { name: 'テンプレートを作成' }).click()
    await expect(page.getByRole('heading', { name: 'テンプレートの作成' })).toBeVisible()

    await page.route('**/admin/templates*', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(json(TEMPLATE, 201))
      } else {
        route.fulfill(json({ items: [TEMPLATE_ROW], total: 1, limit: 50, offset: 0 }))
      }
    })

    await page.locator('#name').fill('月次保守テンプレート')
    await page.locator('#line-0-description').fill('保守サポート')
    await page.locator('#line-0-unit').fill('50000')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page).toHaveURL(/\/templates$/)
    await expect(page.getByRole('heading', { name: 'テンプレート一覧' })).toBeVisible()
  })

  test('requires a name', async ({ page }) => {
    await page.getByRole('link', { name: 'テンプレートを作成' }).click()
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page.getByText('テンプレート名を入力してください。')).toBeVisible()
    await expect(page).toHaveURL(/\/templates\/new$/)
  })

  test('shows an error when the server rejects creation', async ({ page }) => {
    await page.getByRole('link', { name: 'テンプレートを作成' }).click()

    await page.route('**/admin/templates*', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(problem('validation-failed', 422))
      } else {
        route.fulfill(json({ items: [TEMPLATE_ROW], total: 1, limit: 50, offset: 0 }))
      }
    })

    await page.locator('#name').fill('だめなテンプレ')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page.getByText('保存できませんでした。', { exact: false })).toBeVisible()
    await expect(page).toHaveURL(/\/templates\/new$/)
  })

  test('edits a template loaded with its line presets', async ({ page }) => {
    await page.route('**/admin/templates/9', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json(TEMPLATE))
      } else {
        route.fallback()
      }
    })

    await page.getByRole('link', { name: '編集' }).click()
    await expect(page.getByRole('heading', { name: 'テンプレートの編集' })).toBeVisible()
    await expect(page.locator('#name')).toHaveValue('月次保守テンプレート')
    await expect(page.locator('#line-0-description')).toHaveValue('保守サポート')
  })

  test('deletes a template after confirmation', async ({ page }) => {
    await page.route('**/admin/templates/9', (route) => route.fulfill({ status: 204, body: '' }))

    await page.getByRole('button', { name: '削除' }).click()
    await page.getByRole('button', { name: '削除する' }).click()

    await expect(page.getByRole('dialog')).toHaveCount(0)
  })
})
