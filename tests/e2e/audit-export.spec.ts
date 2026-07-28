import { expect, test } from '@playwright/test'
import { json, login } from './helpers/auth'

const LOGS = {
  items: [
    {
      id: 1,
      actor_user_id: 1,
      actor_email: 'admin@example.com',
      organization_id: 1,
      action: 'invoice.issued',
      entity_type: 'invoice',
      entity_id: 5,
      before: null,
      after: { status: 'issued' },
      created_at: '2026-05-31 21:00:12',
    },
  ],
  total: 1,
  limit: 20,
  offset: 0,
}

test('Audit log CSV export downloads a file', async ({ page }) => {
  await page.route('**/admin/audit-logs?**', (r) => r.fulfill(json(LOGS)))
  // Registered after the list route so it takes priority for the export URL.
  await page.route('**/admin/audit-logs/export**', (r) =>
    r.fulfill({
      status: 200,
      contentType: 'text/csv; charset=UTF-8',
      headers: { 'content-disposition': 'attachment; filename="audit-logs-2026-06-04.csv"' },
      body: '﻿日時,アクション\n2026-05-31 21:00:12,invoice.issued\n',
    }),
  )

  await login(page)
  await page.locator('.side').getByText('監査ログ').click()

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('button', { name: 'CSV エクスポート' }).click(),
  ])

  expect(download.suggestedFilename()).toContain('audit-logs')
})
