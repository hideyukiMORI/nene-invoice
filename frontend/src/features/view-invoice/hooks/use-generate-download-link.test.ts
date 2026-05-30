import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toInvoiceId } from '@/entities/invoice'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useGenerateDownloadLink } from './use-generate-download-link'

describe('useGenerateDownloadLink', () => {
  it('only allows generation for an issued invoice', () => {
    const issued = renderHookWithProviders(() => useGenerateDownloadLink(toInvoiceId(1), true))
    expect(issued.result.current.canGenerate).toBe(true)

    const draft = renderHookWithProviders(() => useGenerateDownloadLink(toInvoiceId(1), false))
    expect(draft.result.current.canGenerate).toBe(false)
  })

  it('generates a link and truncates the expiry to a date', async () => {
    server.use(
      http.post('/admin/invoices/:id/download-token', () =>
        HttpResponse.json(
          { url: '/invoices/download/abc123', expires_at: '2026-06-06 12:34:56' },
          { status: 201 },
        ),
      ),
    )

    const { result } = renderHookWithProviders(() => useGenerateDownloadLink(toInvoiceId(1), true))
    expect(result.current.downloadUrl).toBeNull()

    act(() => {
      result.current.generate()
    })

    await waitFor(() => {
      expect(result.current.downloadUrl).toBe('/invoices/download/abc123')
    })
    expect(result.current.expiresAt).toBe('2026-06-06')
  })

  it('surfaces an error message when generation fails', async () => {
    server.use(
      http.post(
        '/admin/invoices/:id/download-token',
        () => new HttpResponse(null, { status: 500 }),
      ),
    )

    const { result } = renderHookWithProviders(() => useGenerateDownloadLink(toInvoiceId(1), true))
    act(() => {
      result.current.generate()
    })

    await waitFor(() => {
      expect(result.current.errorMessage).not.toBeNull()
    })
  })
})
