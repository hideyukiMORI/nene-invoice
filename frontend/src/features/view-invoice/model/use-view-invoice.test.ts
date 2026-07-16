import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toInvoiceId } from '@/entities/invoice'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useViewInvoice } from './use-view-invoice'

describe('useViewInvoice', () => {
  it('loads the invoice with its line items', async () => {
    const { result } = renderHookWithProviders(() => useViewInvoice(toInvoiceId(1)))

    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.invoice.invoice_number).toBe('INV-2026-001')
      expect(result.current.invoice.line_items).toHaveLength(2)
      expect(result.current.invoice.total_cents).toBe(116480)
    }
  })

  it('exposes an error state when the invoice is not found', async () => {
    server.use(http.get('/admin/invoices/:id', () => new HttpResponse(null, { status: 404 })))

    const { result } = renderHookWithProviders(() => useViewInvoice(toInvoiceId(999)))

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
