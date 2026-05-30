import { expect, test } from '@playwright/test'
import { login } from './helpers/auth'

const RECENT_INVOICE = {
  id: 3,
  organization_id: 1,
  client_id: 5,
  status: 'issued',
  invoice_number: 'INV-2026-003',
  is_overdue: true,
  is_qualified_invoice: false,
  subtotal_cents: 200000,
  tax_cents: 20000,
  total_cents: 220000,
}

/** The dashboard is the post-login landing page (no extra navigation needed). */
test.describe('Dashboard', () => {
  test('renders the empty state after login', async ({ page }) => {
    await login(page)

    await expect(page.getByRole('heading', { name: 'ダッシュボード' })).toBeVisible()
    await expect(page.getByText('未払いの請求書はありません。')).toBeVisible()
  })

  test('shows the summary and recent unpaid invoices', async ({ page }) => {
    await login(page, {
      dashboard: {
        unpaid_count: 2,
        overdue_count: 1,
        outstanding_total_cents: 250000,
        recent_unpaid: [RECENT_INVOICE],
      },
    })

    await expect(page.getByText('未払いの請求書はありません。')).toHaveCount(0)
    await expect(page.getByRole('link', { name: 'INV-2026-003' })).toBeVisible()
    await expect(page.getByText('残高合計')).toBeVisible()
  })

  test('shows an error state when the summary fails to load', async ({ page }) => {
    await login(page, { dashboardStatus: 500 })

    await expect(page.getByText('ダッシュボードを取得できませんでした。')).toBeVisible()
  })
})
