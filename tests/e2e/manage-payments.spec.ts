import { expect, test } from '@playwright/test'
import { json, login, problem } from './helpers/auth'

const ISSUED_INVOICE = {
  id: 1,
  organization_id: 1,
  client_id: 5,
  status: 'issued',
  invoice_number: 'INV-2026-001',
  is_overdue: false,
  is_qualified_invoice: true,
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  issued_at: '2026-05-01',
  line_items: [],
}

/** Reaches an issued invoice's payment form through login → invoices list → row. */
test.describe('Manage payments', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/admin/invoices*', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [ISSUED_INVOICE], total: 1, limit: 20, offset: 0 }))
      } else {
        route.fallback()
      }
    })
    await page.route('**/admin/invoices/1', (route) => route.fulfill(json(ISSUED_INVOICE)))
    await page.route('**/admin/invoices/1/payments', (route, request) => {
      if (request.method() === 'GET') {
        route.fulfill(json({ items: [], total_paid_cents: 0 }))
      } else {
        route.fallback()
      }
    })

    await login(page)
    await page.getByRole('link', { name: '請求書', exact: true }).click()
    await page.locator('a[href="/invoices/1"]').click()
    await expect(page.locator('#payment-amount')).toBeVisible()
  })

  test('rejects a non-positive amount before confirming', async ({ page }) => {
    // The amount defaults to 0, which violates the min(1) boundary.
    await page.getByRole('button', { name: '記録する' }).click()

    await expect(page.getByText('金額を正しく入力してください。')).toBeVisible()
    await expect(page.getByRole('dialog')).toHaveCount(0)
  })

  test('records a payment via the confirmation dialog', async ({ page }) => {
    let recorded = false
    await page.route('**/admin/invoices/1/payments', (route, request) => {
      if (request.method() === 'POST') {
        recorded = true
        route.fulfill(
          json(
            {
              payment: {
                id: 1,
                organization_id: 1,
                invoice_id: 1,
                amount_cents: 50000,
                paid_at: '2026-05-30',
                method: 'bank_transfer',
                note: null,
              },
              total_paid_cents: 50000,
            },
            201,
          ),
        )
      } else {
        route.fulfill(
          json({
            items: recorded
              ? [
                  {
                    id: 1,
                    amount_cents: 50000,
                    paid_at: '2026-05-30',
                    method: 'bank_transfer',
                    note: null,
                  },
                ]
              : [],
            total_paid_cents: recorded ? 50000 : 0,
          }),
        )
      }
    })

    await page.locator('#payment-amount').fill('50000')
    await page.locator('#payment-method').selectOption('bank_transfer')
    await page.getByRole('button', { name: '記録する' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog.getByText('を入金として記録しますか？', { exact: false })).toBeVisible()
    await dialog.getByRole('button', { name: '記録する' }).click()

    // The recorded payment appears as a row in the payments table (not the
    // method <option> of the same label).
    await expect(page.getByRole('cell', { name: '銀行振込' })).toBeVisible()
  })

  test('surfaces an error when the amount exceeds the balance', async ({ page }) => {
    await page.route('**/admin/invoices/1/payments', (route, request) => {
      if (request.method() === 'POST') {
        route.fulfill(problem('payment-exceeds-balance', 422))
      } else {
        route.fulfill(json({ items: [], total_paid_cents: 0 }))
      }
    })

    await page.locator('#payment-amount').fill('999999')
    await page.getByRole('button', { name: '記録する' }).click()
    await page.getByRole('dialog').getByRole('button', { name: '記録する' }).click()

    await expect(page.getByText('入金を記録できませんでした。', { exact: false })).toBeVisible()
  })
})
