import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const q = (id: number, num: string, name: string, total: number) => ({
  id,
  organization_id: 1,
  client_id: id,
  client_name: name,
  quote_number: num,
  status: 'sent',
  issued_at: '2026-05-01',
  valid_until: '2026-06-30',
  subtotal_cents: total,
  tax_cents: 0,
  total_cents: total,
})

const ALL = [
  q(1, 'EST-001', '株式会社アルファ', 100000),
  q(2, 'EST-002', '合同会社ベータ', 300000),
]

test.describe('Quote list search / sort', () => {
  test('searches, shows client names, and sorts on header click', async ({ page }) => {
    const seen: string[] = []
    await page.route('**/admin/quotes?**', (route) => {
      const search = new URL(route.request().url()).search
      seen.push(search)
      const items = search.includes('q=') ? [ALL[0]] : ALL
      route.fulfill(json({ items, total: items.length, limit: 20, offset: 0 }))
    })

    await login(page)
    await page.getByRole('link', { name: '見積書', exact: true }).click()

    await expect(page.getByText('株式会社アルファ')).toBeVisible()

    await page.locator('#q-q').fill('アルファ')
    await page.getByRole('button', { name: '絞り込む' }).click()
    await expect.poll(() => seen.at(-1) ?? '').toContain('q=')
    await expect(page.getByText('合同会社ベータ')).toHaveCount(0)

    await page.getByRole('button', { name: '合計' }).click()
    await expect.poll(() => seen.at(-1) ?? '').toContain('sort=total')
  })
})
