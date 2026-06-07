import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useImportClients } from './import'
import type { ClientImportReport } from './import'

const okReport: ClientImportReport = {
  accepted: true,
  dry_run: true,
  format_error: null,
  summary: { rows: 1, created: 1, updated: 0, errors: 0 },
  errors: [],
}

const rejectedReport: ClientImportReport = {
  accepted: false,
  dry_run: false,
  format_error: null,
  summary: { rows: 1, created: 0, updated: 0, errors: 1 },
  errors: [{ row: 2, column: '登録番号', code: 'invalid_registration_number', message: 'NG' }],
}

describe('useImportClients', () => {
  it('resolves the report on a dry-run', async () => {
    server.use(http.post('/admin/clients/import', () => HttpResponse.json(okReport)))

    const { result } = renderHookWithProviders(() => useImportClients())
    const report = await result.current('csv', true)

    expect(report.accepted).toBe(true)
    expect(report.summary.created).toBe(1)
  })

  it('resolves the 422 report (rejected) instead of throwing', async () => {
    server.use(
      http.post('/admin/clients/import', () => HttpResponse.json(rejectedReport, { status: 422 })),
    )

    const { result } = renderHookWithProviders(() => useImportClients())
    const report = await result.current('csv', false)

    expect(report.accepted).toBe(false)
    expect(report.errors[0]?.code).toBe('invalid_registration_number')
  })
})
