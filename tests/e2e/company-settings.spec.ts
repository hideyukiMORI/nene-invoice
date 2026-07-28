import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const SETTINGS = {
  organization_id: 1,
  legal_name: 'テスト株式会社',
  address: null,
  phone: null,
  email: null,
  registration_number: null,
  bank_name: null,
  bank_branch: null,
  account_type: null,
  account_number: null,
}

/** Reaches /settings through login → "設定" nav; the form is prefilled from GET. */
test.describe('Company settings', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/company-settings', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json(SETTINGS))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '設定' }).click()
    await expect(page.locator('#legal_name')).toHaveValue('テスト株式会社')
  })

  test('requires a legal name', async ({ page }) => {
    await page.locator('#legal_name').fill('')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page.getByText('法人名は必須です。')).toBeVisible()
  })

  test('saves and shows a confirmation message', async ({ page }) => {
    await page.route('**/admin/company-settings', (route, request) => {
      if (request.method() === 'PUT') {
        route.fulfill(json({ ...SETTINGS, legal_name: '株式会社あやね' }))
      } else {
        route.fulfill(json(SETTINGS))
      }
    })

    await page.locator('#legal_name').fill('株式会社あやね')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page.getByText('保存しました。')).toBeVisible()
  })

  test('shows an error when the server rejects the input', async ({ page }) => {
    await page.route('**/admin/company-settings', (route, request) => {
      if (request.method() === 'PUT') {
        route.fulfill(problem('invalid-registration-number', 422))
      } else {
        route.fulfill(json(SETTINGS))
      }
    })

    await page.locator('#registration_number').fill('BAD')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page.getByText('保存できませんでした。', { exact: false })).toBeVisible()
  })
})
