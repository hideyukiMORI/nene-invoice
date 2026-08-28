import type { Page } from '@playwright/test'

/** JSON helper for `page.route` fulfilments. */
export function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) }
}

/** RFC 7807 problem+json helper for error fulfilments. */
export function problem(slug: string, status: number) {
  return {
    status,
    contentType: 'application/problem+json',
    body: JSON.stringify({
      type: `https://nene-invoice.dev/problems/${slug}`,
      title: 'Error',
      status,
    }),
  }
}

const CURRENT_USER = { id: 1, email: 'admin@example.com', role: 'admin', organization_id: 1 }
const EMPTY_DASHBOARD = {
  unpaid_count: 0,
  overdue_count: 0,
  outstanding_total_cents: 0,
  recent_unpaid: [],
  received_this_month_cents: 0,
  received_last_month_cents: 0,
  aging: { current: 0, overdue_1_30: 0, overdue_31_plus: 0 },
  billed_this_month_cents: 0,
  billed_last_month_cents: 0,
  monthly_billed: [],
  billed_prev_year_month_cents: 0,
  billed_daily_current: [],
  billed_daily_prev_month: [],
}

/**
 * Logs in through the real sign-in form (stubbing `/auth/login`, `/admin/me`,
 * `/admin/dashboard`) and waits for the authenticated app shell. After this the
 * in-memory token is set; reach features by clicking nav links, never by
 * `page.goto` (a full reload clears the token).
 *
 * The landing dashboard is stubbed empty by default; pass `dashboard` to render
 * a populated summary, or `dashboardStatus` to exercise its error state.
 */
export async function login(
  page: Page,
  options: { dashboard?: unknown; dashboardStatus?: number } = {},
): Promise<void> {
  await page.route('**/auth/login', (route) => route.fulfill(json({ token: 'e2e-token' })))
  await page.route('**/admin/me', (route) => route.fulfill(json(CURRENT_USER)))
  await page.route('**/admin/dashboard', (route) => {
    if (options.dashboardStatus !== undefined && options.dashboardStatus >= 400) {
      route.fulfill({
        status: options.dashboardStatus,
        contentType: 'application/json',
        body: '{}',
      })
    } else {
      route.fulfill(json(options.dashboard ?? EMPTY_DASHBOARD))
    }
  })

  await page.goto('/')
  await page.locator('#email').fill('admin@example.com')
  await page.locator('#password').fill('correct-horse')
  await page.getByRole('button', { name: 'ログイン' }).click()

  // The primary nav only renders once the auth gate reveals the app shell.
  await page.getByRole('link', { name: '取引先' }).waitFor()
}

/** Issuer profile stub. Only `registration_number` matters to most specs. */
const COMPANY_SETTINGS = {
  organization_id: 1,
  legal_name: 'テスト株式会社',
  address: null,
  phone: null,
  email: null,
  registration_number: null as string | null,
  bank_name: null,
  bank_branch: null,
  account_type: null,
  account_number: null,
}

/**
 * Stubs GET /admin/company-settings.
 *
 * 🔴 Any spec that reaches the invoice detail needs this (#771). The issue
 * action derives 適格請求書 eligibility from the registration number, and an
 * UNSTUBBED request does not fail loudly here — the preview server answers the
 * XHR with `index.html`, the query errors, and eligibility stays unknown, so
 * the button renders plain and disabled. That reads as "the label changed"
 * rather than "the stub is missing", which is what it actually is.
 *
 * Pass a `T`+13-digit number for an issuer that can hand out a 適格請求書, or
 * `null` for one that cannot.
 */
export async function stubCompanySettings(
  page: Page,
  registrationNumber: string | null,
): Promise<void> {
  await page.route('**/admin/company-settings', (route, request) => {
    if (request.method() === 'GET') {
      route.fulfill(json({ ...COMPANY_SETTINGS, registration_number: registrationNumber }))
    } else {
      route.fallback()
    }
  })
}
