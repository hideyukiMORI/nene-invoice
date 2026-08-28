import { expect, test } from '@playwright/test'
import { json, login, problem, stubCompanySettings } from './helpers/auth'

const REGISTERED = 'T1234567890123'

const DRAFT_INVOICE = {
  id: 1,
  organization_id: 1,
  client_id: 5,
  status: 'draft',
  invoice_number: null,
  is_overdue: false,
  is_qualified_invoice: true,
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  line_items: [],
}

/** Reaches a draft invoice's detail through login → invoices list → the row link. */
async function openDraftInvoice(
  page: Parameters<typeof login>[0],
  registrationNumber: string | null,
): Promise<void> {
  await page.route('**/admin/invoices*', (route, request) => {
    if (request.method() === 'GET') {
      route.fulfill(json({ items: [DRAFT_INVOICE], total: 1, limit: 20, offset: 0 }))
    } else {
      route.fallback()
    }
  })
  await page.route('**/admin/invoices/1', (route) => route.fulfill(json(DRAFT_INVOICE)))
  await page.route('**/admin/invoices/1/payments', (route) =>
    route.fulfill(json({ items: [], total_paid_cents: 0 })),
  )
  await stubCompanySettings(page, registrationNumber)

  await login(page)
  await page.getByRole('link', { name: '請求書', exact: true }).click()
  await page.locator('a[href="/invoices/1"]').click()
}

test.describe('Issue invoice — 登録番号あり', () => {
  test.beforeEach(async ({ page }) => {
    await openDraftInvoice(page, REGISTERED)
    await expect(page.getByRole('button', { name: '発行する（適格請求書）' })).toBeVisible()
  })

  test('opens an irreversible-issue confirmation dialog', async ({ page }) => {
    await page.getByRole('button', { name: '発行する（適格請求書）' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText('請求書を発行しますか？')).toBeVisible()
  })

  test('surfaces the registration-number recovery when the API rejects the qualified issue', async ({
    page,
  }) => {
    await page.route('**/admin/invoices/1/issue', (route) =>
      route.fulfill(problem('qualified-invoice-incomplete', 422)),
    )

    await page.getByRole('button', { name: '発行する（適格請求書）' }).click()
    await page.getByRole('dialog').getByRole('button', { name: '発行する（適格請求書）' }).click()

    await expect(
      page.getByText('発行できませんでした。会社情報の登録番号をご確認ください。'),
    ).toBeVisible()
    // 型2 recovery affordance: a link to the company settings.
    await expect(page.getByRole('button', { name: '会社設定を開く →' })).toBeVisible()
  })
})

/**
 * 🔴 #771. Before the fix, an issuer with no registration number could not
 * issue ANYTHING: the UI only knew how to send `qualified: true`, which the API
 * refuses when the number is empty. The spec that used to live here asserted
 * that dead end as if it were the product — it drove the qualified button and
 * expected the 422. It is replaced, not deleted: the same starting state now
 * has to reach an issued invoice.
 */
test.describe('Issue invoice — 登録番号なし', () => {
  test.beforeEach(async ({ page }) => {
    await openDraftInvoice(page, null)
  })

  test('offers a plain issue action, with no 適格請求書 claim', async ({ page }) => {
    await expect(page.getByRole('button', { name: '発行する' })).toBeEnabled()
    await expect(page.getByRole('button', { name: '発行する（適格請求書）' })).toHaveCount(0)
  })

  test('says the invoice will not be a 適格請求書 before issuing it', async ({ page }) => {
    await page.getByRole('button', { name: '発行する' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText('適格請求書ではない請求書として発行します')).toBeVisible()
  })

  test('issues as NOT qualified', async ({ page }) => {
    let issuedBody: Record<string, unknown> | null = null
    await page.route('**/admin/invoices/1/issue', (route, request) => {
      issuedBody = request.postDataJSON() as Record<string, unknown>
      route.fulfill(
        json({
          ...DRAFT_INVOICE,
          status: 'issued',
          invoice_number: 'INV-2026-001',
          is_qualified_invoice: false,
        }),
      )
    })

    await page.getByRole('button', { name: '発行する' }).click()
    await page.getByRole('dialog').getByRole('button', { name: '発行する' }).click()

    await expect(page.getByText('INV-2026-001 を発行しました。')).toBeVisible()
    expect(issuedBody).toMatchObject({ qualified: false })
  })
})
