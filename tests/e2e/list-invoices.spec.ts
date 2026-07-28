import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const invoice = (id: number, number: string) => ({
  id,
  organization_id: 1,
  client_id: 5,
  status: 'issued',
  invoice_number: number,
  is_overdue: false,
  is_qualified_invoice: false,
  subtotal_cents: 1000,
  tax_cents: 100,
  total_cents: 1100,
})

/** Reaches the invoices list through login → "請求書" nav. */
test.describe('List invoices', () => {
  test('shows the empty state when there are no invoices', async ({ page }) => {
    await page.route('**/admin/invoices*', (route) =>
      route.fulfill(json({ items: [], total: 0, limit: 20, offset: 0 })),
    )

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()

    await expect(page.getByText('請求書がまだありません。')).toBeVisible()
  })

  test('paginates across multiple pages', async ({ page }) => {
    await page.route('**/admin/invoices*', (route, request) => {
      const offset = Number(new URL(request.url()).searchParams.get('offset') ?? '0')
      const items = offset === 0 ? [invoice(1, 'INV-2026-001')] : [invoice(2, 'INV-2026-002')]
      route.fulfill(json({ items, total: 25, limit: 20, offset }))
    })

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()

    await expect(page.getByRole('link', { name: 'INV-2026-001' })).toBeVisible()
    await expect(page.getByText('1 / 2 ページ')).toBeVisible()

    await page.getByRole('button', { name: '次のページ' }).click()

    await expect(page.getByRole('link', { name: 'INV-2026-002' })).toBeVisible()
    await expect(page.getByText('2 / 2 ページ')).toBeVisible()
  })

  test('downloads invoices CSV', async ({ page }) => {
    await page.route('**/admin/invoices/export', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'text/csv; charset=UTF-8',
        body: Buffer.from('\xEF\xBB\xBF請求書番号\nINV-2026-001\n'),
      }),
    )

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: '請求書 CSV' }).click(),
    ])

    expect(download.suggestedFilename()).toMatch(/invoices-.*\.csv/)
  })

  test('downloads payments CSV', async ({ page }) => {
    await page.route('**/admin/payments/export', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'text/csv; charset=UTF-8',
        body: Buffer.from('\xEF\xBB\xBF請求書番号\nINV-2026-001\n'),
      }),
    )

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: '入金 CSV' }).click(),
    ])

    expect(download.suggestedFilename()).toMatch(/payments-.*\.csv/)
  })
})
