import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { EMPTY_INVOICE_FILTERS } from './model'
import type { InvoiceSort } from './model'
import { useExportInvoicesCsv, useExportPaymentsCsv } from './export'

const NO_SORT: InvoiceSort = { field: null, order: 'desc' }

describe('useExportInvoicesCsv', () => {
  it('starts in idle state', () => {
    const { result } = renderHookWithProviders(() => useExportInvoicesCsv(EMPTY_INVOICE_FILTERS, NO_SORT))
    expect(result.current.isDownloading).toBe(false)
    expect(result.current.errorMessage).toBeNull()
  })

  it('clears error and triggers download on success', async () => {
    server.use(
      http.get(
        '/admin/invoices/export',
        () =>
          new HttpResponse('\xEF\xBB\xBF請求書番号\n', {
            headers: { 'Content-Type': 'text/csv; charset=UTF-8' },
          }),
      ),
    )

    const { result } = renderHookWithProviders(() => useExportInvoicesCsv(EMPTY_INVOICE_FILTERS, NO_SORT))

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
    server.use(http.get('/admin/invoices/export', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useExportInvoicesCsv(EMPTY_INVOICE_FILTERS, NO_SORT))

    act(() => {
      result.current.download()
    })

    await waitFor(() => {
      expect(result.current.errorMessage).not.toBeNull()
    })
  })
})

describe('useExportPaymentsCsv', () => {
  it('starts in idle state', () => {
    const { result } = renderHookWithProviders(() => useExportPaymentsCsv())
    expect(result.current.isDownloading).toBe(false)
    expect(result.current.errorMessage).toBeNull()
  })

  it('clears error and downloads on success', async () => {
    server.use(
      http.get(
        '/admin/payments/export',
        () =>
          new HttpResponse('\xEF\xBB\xBF請求書番号\n', {
            headers: { 'Content-Type': 'text/csv; charset=UTF-8' },
          }),
      ),
    )

    const { result } = renderHookWithProviders(() => useExportPaymentsCsv())

    act(() => {
      result.current.download()
    })

    await waitFor(() => {
      expect(result.current.isDownloading).toBe(false)
    })

    expect(result.current.errorMessage).toBeNull()
  })
})
