import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const CLIENT = {
  id: 5,
  organization_id: 1,
  name: '得意先ABC',
  contact_name: '山田',
  email: null,
  billing_address: null,
  registration_number: 'T9876543210123',
}

/** Reaches /clients/5/edit through login → clients list → "編集". */
test.describe('Edit client', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/clients*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [CLIENT], total: 1, limit: 100, offset: 0 }))
      } else {
        route.fallback()
      }
    })
    await page.route('**/admin/clients/5', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json(CLIENT))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '取引先', exact: true }).click()
    await page.getByRole('link', { name: '編集' }).click()
    await expect(page.getByRole('heading', { name: '取引先の編集' })).toBeVisible()
  })

  test('prefills the form from the loaded client', async ({ page }) => {
    await expect(page.locator('#name')).toHaveValue('得意先ABC')
    await expect(page.locator('#registration_number')).toHaveValue('T9876543210123')
  })

  test('requires a name', async ({ page }) => {
    await page.locator('#name').fill('')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page.getByText('名称を入力してください。')).toBeVisible()
  })

  test('saves and returns to the list', async ({ page }) => {
    await page.route('**/admin/clients/5', (route, request) => {
      if (request.method() === 'PATCH') {
        route.fulfill(json({ ...CLIENT, name: '得意先ABC（改）' }))
      } else {
        route.fulfill(json(CLIENT))
      }
    })

    await page.locator('#name').fill('得意先ABC（改）')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page).toHaveURL(/\/clients$/)
  })

  test('shows an error when the server rejects the input', async ({ page }) => {
    await page.route('**/admin/clients/5', (route, request) => {
      if (request.method() === 'PATCH') {
        route.fulfill(problem('invalid-registration-number', 422))
      } else {
        route.fulfill(json(CLIENT))
      }
    })

    await page.locator('#registration_number').fill('BAD')
    await page.getByRole('button', { name: '保存する' }).click()

    await expect(page.getByText('保存できませんでした。', { exact: false })).toBeVisible()
  })
})
