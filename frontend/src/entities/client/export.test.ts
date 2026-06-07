import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { EMPTY_CLIENT_FILTERS } from './model'
import type { ClientSort } from './model'
import { useExportClientsCsv } from './export'

const NO_SORT: ClientSort = { field: null, order: 'asc' }

describe('useExportClientsCsv', () => {
  it('starts in idle state', () => {
    const { result } = renderHookWithProviders(() =>
      useExportClientsCsv(EMPTY_CLIENT_FILTERS, NO_SORT),
    )
    expect(result.current.isDownloading).toBe(false)
    expect(result.current.errorMessage).toBeNull()
  })

  it('clears error and triggers download on success', async () => {
    server.use(
      http.get(
        '/admin/clients/export',
        () =>
          new HttpResponse('\xEF\xBB\xBF取引先名\n', {
            headers: { 'Content-Type': 'text/csv; charset=UTF-8' },
          }),
      ),
    )

    const { result } = renderHookWithProviders(() =>
      useExportClientsCsv(EMPTY_CLIENT_FILTERS, NO_SORT),
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
    server.use(http.get('/admin/clients/export', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() =>
      useExportClientsCsv(EMPTY_CLIENT_FILTERS, NO_SORT),
    )

    act(() => {
      result.current.download()
    })

    await waitFor(() => {
      expect(result.current.errorMessage).not.toBeNull()
    })
  })
})
