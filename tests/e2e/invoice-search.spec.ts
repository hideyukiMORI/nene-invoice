import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const inv = (id: number, num: string, name: string, total: number) => ({
  id,
  organization_id: 1,
  client_id: id,
  client_name: name,
  status: 'issued',
  is_overdue: false,
  is_qualified_invoice: true,
  invoice_number: num,
  issued_at: '2026-05-01',
  due_at: '2026-06-30',
  subtotal_cents: total,
  tax_cents: 0,
  total_cents: total,
  outstanding_cents: total,
})

const ALL = [
  inv(1, 'INV-001', '株式会社アルファ', 100000),
  inv(2, 'INV-002', '合同会社ベータ', 300000),
]

test.describe('Invoice list search / filter / sort', () => {
  test('sends the search query and shows client names, and sorts on header click', async ({
    page,
  }) => {
    const seen: string[] = []
    await page.route('**/admin/invoices?**', (route) => {
      const search = new URL(route.request().url()).search
      seen.push(search)
      const items = search.includes('q=') ? [ALL[0]] : ALL
      route.fulfill(json({ items, total: items.length, limit: 20, offset: 0 }))
    })

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()

    // Client names render (not ids).
    await expect(page.getByText('株式会社アルファ')).toBeVisible()

    // Search narrows the list and sends q=.
    await page.locator('#inv-q').fill('アルファ')
    await page.getByRole('button', { name: '絞り込む' }).click()
    await expect.poll(() => seen.at(-1) ?? '').toContain('q=')
    await expect(page.getByText('合同会社ベータ')).toHaveCount(0)

    // Clicking a sortable header issues a sort param.
    await page.getByRole('button', { name: '合計' }).click()
    await expect.poll(() => seen.at(-1) ?? '').toContain('sort=total')
  })
})
