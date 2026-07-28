import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const CLIENT = {
  id: 9,
  organization_id: 1,
  name: '新規取引先',
  contact_name: null,
  email: null,
  billing_address: null,
  registration_number: null,
}

/** Reaches /clients/new through login → clients list → "取引先を作成". */
test.describe('Create client', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/clients', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '取引先' }).click()
    await page.getByRole('link', { name: '取引先を作成' }).click()
    await expect(page.getByRole('heading', { name: '取引先の作成' })).toBeVisible()
  })

  test('requires a name', async ({ page }) => {
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page.getByText('名称を入力してください。')).toBeVisible()
    await expect(page).toHaveURL(/\/clients\/new$/)
  })

  test('creates a client and returns to the list', async ({ page }) => {
    await page.route('**/admin/clients', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(json(CLIENT, 201))
      } else {
        route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 }))
      }
    })

    await page.locator('#name').fill('新規取引先')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page).toHaveURL(/\/clients$/)
    await expect(page.getByRole('heading', { name: '取引先一覧' })).toBeVisible()
  })

  test('shows an error when the server rejects the input', async ({ page }) => {
    await page.route('**/admin/clients', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(problem('invalid-registration-number', 422))
      } else {
        route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 }))
      }
    })

    await page.locator('#name').fill('株式会社テスト')
    await page.locator('#registration_number').fill('BAD')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page.getByText('取引先を作成できませんでした。', { exact: false })).toBeVisible()
    await expect(page).toHaveURL(/\/clients\/new$/)
  })
})
