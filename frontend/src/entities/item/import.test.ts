import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import type { CsvImportReport } from '@/shared/lib/csv-import'
import { useImportItems } from './import'

const okReport: CsvImportReport = {
  accepted: true,
  dry_run: true,
  format_error: null,
  summary: { rows: 1, created: 1, updated: 0, errors: 0 },
  errors: [],
}

const rejectedReport: CsvImportReport = {
  accepted: false,
  dry_run: false,
  format_error: null,
  summary: { rows: 1, created: 0, updated: 0, errors: 1 },
  errors: [{ row: 2, column: '標準税率', code: 'invalid_tax_rate', message: 'NG' }],
}

describe('useImportItems', () => {
  it('resolves the report on a dry-run', async () => {
    server.use(http.post('/admin/items/import', () => HttpResponse.json(okReport)))

    const { result } = renderHookWithProviders(() => useImportItems())
    const report = await result.current('csv', true)

    expect(report.accepted).toBe(true)
    expect(report.summary.created).toBe(1)
  })

  it('resolves the 422 report (rejected) instead of throwing', async () => {
    server.use(
      http.post('/admin/items/import', () => HttpResponse.json(rejectedReport, { status: 422 })),
    )

    const { result } = renderHookWithProviders(() => useImportItems())
    const report = await result.current('csv', false)

    expect(report.accepted).toBe(false)
    expect(report.errors[0]?.code).toBe('invalid_tax_rate')
  })
})
