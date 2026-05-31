import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toQuoteId } from './ids'
import { useDownloadQuotePdf } from './download'

describe('useDownloadQuotePdf', () => {
  it('starts in idle state', () => {
    const { result } = renderHookWithProviders(() =>
      useDownloadQuotePdf(toQuoteId(1), 'EST-2026-001'),
    )
    expect(result.current.isDownloading).toBe(false)
    expect(result.current.errorMessage).toBeNull()
  })

  it('downloads and clears error on success', async () => {
    server.use(
      http.get(
        '/admin/quotes/:id/pdf',
        () =>
          new HttpResponse('%PDF-1.4 fake', {
            headers: { 'Content-Type': 'application/pdf' },
          }),
      ),
    )

    const { result } = renderHookWithProviders(() =>
      useDownloadQuotePdf(toQuoteId(1), 'EST-2026-001'),
    )

    act(() => {
      result.current.download()
    })

    expect(result.current.isDownloading).toBe(true)

    await waitFor(() => {
      expect(result.current.isDownloading).toBe(false)
    })

    expect(result.current.errorMessage).toBeNull()
  })

  it('sets errorMessage on server error', async () => {
    server.use(http.get('/admin/quotes/:id/pdf', () => new HttpResponse(null, { status: 404 })))

    const { result } = renderHookWithProviders(() =>
      useDownloadQuotePdf(toQuoteId(1), 'EST-2026-001'),
    )

    act(() => {
      result.current.download()
    })

    await waitFor(() => {
      expect(result.current.errorMessage).not.toBeNull()
    })
  })

  it('does not restart if already downloading', async () => {
    let callCount = 0
    server.use(
      http.get('/admin/quotes/:id/pdf', async () => {
        callCount++
        await new Promise((resolve) => setTimeout(resolve, 50))
        return new HttpResponse('%PDF fake', { headers: { 'Content-Type': 'application/pdf' } })
      }),
    )

    const { result } = renderHookWithProviders(() =>
      useDownloadQuotePdf(toQuoteId(1), 'EST-2026-001'),
    )

    act(() => {
      result.current.download()
    })
    act(() => {
      result.current.download()
    }) // second call ignored

    await waitFor(() => {
      expect(result.current.isDownloading).toBe(false)
    })

    expect(callCount).toBe(1)
  })
})
