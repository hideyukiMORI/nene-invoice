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
}

/**
 * Logs in through the real sign-in form (stubbing `/auth/login`, `/admin/me`,
 * `/admin/dashboard`) and waits for the authenticated app shell. After this the
 * in-memory token is set; reach features by clicking nav links, never by
 * `page.goto` (a full reload clears the token).
 */
export async function login(page: Page): Promise<void> {
  await page.route('**/auth/login', (route) => route.fulfill(json({ token: 'e2e-token' })))
  await page.route('**/admin/me', (route) => route.fulfill(json(CURRENT_USER)))
  await page.route('**/admin/dashboard', (route) => route.fulfill(json(EMPTY_DASHBOARD)))

  await page.goto('/')
  await page.locator('#email').fill('admin@example.com')
  await page.locator('#password').fill('correct-horse')
  await page.getByRole('button', { name: 'ログイン' }).click()

  // The primary nav only renders once the auth gate reveals the app shell.
  await page.getByRole('link', { name: '取引先' }).waitFor()
}
