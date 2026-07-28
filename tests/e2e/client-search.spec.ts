import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const c = (id: number, name: string, contact: string) => ({
  id,
  organization_id: 1,
  name,
  contact_name: contact,
  email: null,
  billing_address: null,
  registration_number: null,
})

const ALL = [c(1, '株式会社アルファ', '田中 一郎'), c(2, '合同会社ベータ', '佐藤 花子')]

test.describe('Client list search / sort', () => {
  test('searches and sorts on header click', async ({ page }) => {
    const seen: string[] = []
    await page.route('**/admin/clients?**', (route) => {
      const search = new URL(route.request().url()).search
      seen.push(search)
      const items = search.includes('q=') ? [ALL[0]] : ALL
      route.fulfill(json({ items, total: items.length, limit: 100, offset: 0 }))
    })

    await login(page)
    await page.getByRole('link', { name: '取引先' }).click()
    await expect(page.getByText('合同会社ベータ')).toBeVisible()

    await page.locator('#client-q').fill('アルファ')
    await page.getByRole('button', { name: '絞り込む' }).click()
    await expect.poll(() => seen.at(-1) ?? '').toContain('q=')
    await expect(page.getByText('合同会社ベータ')).toHaveCount(0)

    await page.getByRole('button', { name: '名称' }).click()
    await expect.poll(() => seen.at(-1) ?? '').toContain('sort=name')
  })
})
