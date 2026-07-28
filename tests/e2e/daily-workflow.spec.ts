import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

/**
 * 正常系「1日分の業務」シナリオ。
 *
 * 朝の出社からログアウトまでを 1 本のストーリーとして通すデモ向け e2e。
 * 各エンドポイントは `page.route` でスタブするが、1 日の流れに沿って状態が
 * 変わるよう（取引先が増える / 請求書が draft→issued→入金済みになる）
 * ステートフルにモックしている。UI モード（`npx playwright test --ui`）で
 * 目視しながら流すことを想定したシナリオ。
 *
 * 流れ:
 *   1. ログイン → ダッシュボードで当日の状況を確認
 *   2. 新規取引先（株式会社ネネ商事）を登録
 *   3. その取引先あての見積書を作成
 *   4. 受注 → 請求書を作成
 *   5. 適格請求書として発行
 *   6. 入金を記録
 *   7. ログアウト
 */

const COMPANY = {
  id: 1,
  organization_id: 1,
  name: '株式会社ネネ商事',
  contact_name: '根根 太郎',
  email: 'keiri@nene-shoji.example.jp',
  billing_address: '東京都千代田区1-1-1',
  registration_number: 'T1234567890123',
}

/** 朝イチのダッシュボードに出す当日の状況サマリ。 */
const MORNING_DASHBOARD = {
  unpaid_count: 2,
  overdue_count: 1,
  outstanding_total_cents: 250000,
  recent_unpaid: [
    {
      id: 3,
      organization_id: 1,
      client_id: 5,
      status: 'issued',
      invoice_number: 'INV-2026-003',
      is_overdue: true,
      is_qualified_invoice: true,
      subtotal_cents: 200000,
      tax_cents: 20000,
      total_cents: 220000,
    },
  ],
  received_this_month_cents: 80000,
  received_last_month_cents: 50000,
  aging: { current: 100000, overdue_1_30: 100000, overdue_31_plus: 50000 },
  billed_this_month_cents: 330000,
  billed_last_month_cents: 210000,
  monthly_billed: [
    { month: '2026-05', billed_cents: 210000, count: 3 },
    { month: '2026-06', billed_cents: 330000, count: 4 },
  ],
  billed_prev_year_month_cents: 180000,
  billed_daily_current: [
    { day: 1, cumulative_cents: 100000 },
    { day: 9, cumulative_cents: 330000 },
  ],
  billed_daily_prev_month: [{ day: 1, cumulative_cents: 210000 }],
}

test.describe('1日の業務（正常系）', () => {
  test('ログイン → 取引先登録 → 見積 → 請求 → 発行 → 入金 → ログアウト', async ({ page }) => {
    // --- ステート（1日の中で変化する） ---
    const clients: (typeof COMPANY)[] = []
    let quoteCreated = false
    let invoiceCreated = false
    let invoiceStatus: 'draft' | 'issued' = 'draft'
    let paymentRecorded = false

    const invoicePayload = () => ({
      id: 1,
      organization_id: 1,
      client_id: 1,
      status: invoiceStatus,
      invoice_number: invoiceStatus === 'issued' ? 'INV-2026-010' : null,
      is_overdue: false,
      is_qualified_invoice: true,
      subtotal_cents: 100000,
      tax_cents: 10000,
      total_cents: 110000,
      issued_at: invoiceStatus === 'issued' ? '2026-06-09' : undefined,
      line_items: [
        {
          id: 1,
          description: 'Webサイト設計作業',
          quantity: 1,
          unit_price_cents: 100000,
          tax_rate_bps: 1000,
        },
      ],
    })

    const quotePayload = () => ({
      id: 1,
      organization_id: 1,
      client_id: 1,
      quote_number: 'EST-2026-008',
      status: 'draft',
      subtotal_cents: 100000,
      tax_cents: 10000,
      total_cents: 110000,
      line_items: [
        {
          id: 1,
          description: 'Webサイト設計作業',
          quantity: 1,
          unit_price_cents: 100000,
          tax_rate_bps: 1000,
        },
      ],
    })

    // --- フォーム共通のスタブ（テンプレート・履歴サジェスト） ---
    await page.route('**/admin/line-items/suggestions*', (route) =>
      route.fulfill(json({ items: [] })),
    )
    await page.route('**/admin/templates*', (route) =>
      route.fulfill(json({ items: [], total: 0, limit: 100, offset: 0 })),
    )

    // --- 取引先 API（登録すると一覧に増える） ---
    await page.route('**/admin/clients/*', (route) => route.fulfill(json(clients[0] ?? COMPANY)))
    await page.route('**/admin/clients*', (route, request) => {
      if (request.method() === 'POST') {
        clients.push(COMPANY)
        route.fulfill(json(COMPANY, 201))
      } else {
        route.fulfill(json({ items: clients, total: clients.length, limit: 100, offset: 0 }))
      }
    })

    // --- 見積 API ---
    await page.route('**/admin/quotes/1', (route) => route.fulfill(json(quotePayload())))
    await page.route('**/admin/quotes*', (route, request) => {
      if (request.method() === 'POST') {
        quoteCreated = true
        route.fulfill(json(quotePayload(), 201))
      } else {
        const items = quoteCreated ? [quotePayload()] : []
        route.fulfill(json({ items, total: items.length, limit: 20, offset: 0 }))
      }
    })

    // --- 請求 API（draft → issued、入金記録まで） ---
    await page.route('**/admin/invoices/1/issue', (route) => {
      invoiceStatus = 'issued'
      route.fulfill(json(invoicePayload()))
    })
    await page.route('**/admin/invoices/1/payments', (route, request) => {
      if (request.method() === 'POST') {
        paymentRecorded = true
        route.fulfill(
          json(
            {
              payment: {
                id: 1,
                organization_id: 1,
                invoice_id: 1,
                amount_cents: 110000,
                paid_at: '2026-06-09',
                method: 'bank_transfer',
                note: null,
              },
              total_paid_cents: 110000,
            },
            201,
          ),
        )
      } else {
        route.fulfill(
          json({
            items: paymentRecorded
              ? [
                  {
                    id: 1,
                    amount_cents: 110000,
                    paid_at: '2026-06-09',
                    method: 'bank_transfer',
                    note: null,
                  },
                ]
              : [],
            total_paid_cents: paymentRecorded ? 110000 : 0,
          }),
        )
      }
    })
    await page.route('**/admin/invoices/1', (route) => route.fulfill(json(invoicePayload())))
    await page.route('**/admin/invoices*', (route, request) => {
      if (request.method() === 'POST') {
        invoiceCreated = true
        route.fulfill(json(invoicePayload(), 201))
      } else {
        const items = invoiceCreated ? [invoicePayload()] : []
        route.fulfill(json({ items, total: items.length, limit: 20, offset: 0 }))
      }
    })

    // === 1. 出社・ログイン → ダッシュボードで当日の状況を確認 ===
    await login(page, { dashboard: MORNING_DASHBOARD })
    await expect(page.getByRole('heading', { name: 'ダッシュボード' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'INV-2026-003' })).toBeVisible()

    // === 2. 新規取引先（株式会社ネネ商事）を登録 ===
    await page.getByRole('link', { name: '取引先' }).click()
    await page.getByRole('link', { name: '取引先を作成' }).click()
    await expect(page.getByRole('heading', { name: '取引先の作成' })).toBeVisible()

    await page.locator('#name').fill('株式会社ネネ商事')
    await page.locator('#contact_name').fill('根根 太郎')
    await page.locator('#email').fill('keiri@nene-shoji.example.jp')
    await page.locator('#registration_number').fill('T1234567890123')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page).toHaveURL(/\/clients$/)
    await expect(page.getByRole('heading', { name: '取引先一覧' })).toBeVisible()
    await expect(page.getByText('株式会社ネネ商事')).toBeVisible()

    // === 3. その取引先あての見積書を作成 ===
    await page.getByRole('link', { name: '見積書', exact: true }).click()
    await page.getByRole('link', { name: '見積書を作成' }).click()
    await expect(page.getByRole('heading', { name: '見積書を作成' })).toBeVisible()

    await page.locator('#client_id').fill('ネネ')
    await page.getByRole('option', { name: '株式会社ネネ商事' }).click()
    await page.locator('#line-0-description').fill('Webサイト設計作業')
    await page.locator('#line-0-unit').fill('100000')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page).toHaveURL(/\/quotes\/1$/)

    // === 4. 受注 → 請求書を作成 ===
    await page.getByRole('link', { name: '請求書', exact: true }).click()
    await page.getByRole('link', { name: '請求書を作成' }).click()
    await expect(page.getByRole('heading', { name: '請求書の作成' })).toBeVisible()

    await page.locator('#client_id').fill('ネネ')
    await page.getByRole('option', { name: '株式会社ネネ商事' }).click()
    await page.locator('#line-0-description').fill('Webサイト設計作業')
    await page.locator('#line-0-unit').fill('100000')
    await page.getByRole('button', { name: '作成する' }).click()

    await expect(page).toHaveURL(/\/invoices\/1$/)

    // === 5. 適格請求書として発行（取り消し不可の確認ダイアログ） ===
    await page.getByRole('button', { name: '発行する（適格請求書）' }).click()
    const issueDialog = page.getByRole('dialog')
    await expect(issueDialog.getByText('請求書を発行しますか？')).toBeVisible()
    await issueDialog.getByRole('button', { name: '発行する（適格請求書）' }).click()

    // 発行後は請求書番号が採番される。
    await expect(page.getByRole('heading', { name: 'INV-2026-010' })).toBeVisible()

    // === 6. 入金を記録（全額・銀行振込） ===
    await page.locator('#payment-amount').fill('110000')
    await page.locator('#payment-method').selectOption('bank_transfer')
    await page.getByRole('button', { name: '記録する' }).click()

    const payDialog = page.getByRole('dialog')
    await expect(payDialog.getByText('を入金として記録しますか？', { exact: false })).toBeVisible()
    await payDialog.getByRole('button', { name: '記録する' }).click()

    // 入金が明細行として現れる。
    await expect(page.getByRole('cell', { name: '銀行振込' })).toBeVisible()

    // === 7. 退社・ログアウト ===
    await page.getByRole('button', { name: 'ログアウト' }).click()
    await expect(page.locator('#email')).toBeVisible()
    await expect(page.getByRole('button', { name: 'ログイン' })).toBeVisible()
    await expect(page.getByRole('link', { name: '取引先' })).toHaveCount(0)
  })
})
