import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toInvoiceId } from '@/entities/invoice'
import { server } from '@tests/msw/server'
import { buildInvoiceWithLinesDto } from '@tests/factories/invoice'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useIssueInvoice } from './use-issue-invoice'

describe('useIssueInvoice (feature)', () => {
  it('allows issuing a draft invoice', async () => {
    server.use(
      http.get('/admin/invoices/:id', () =>
        HttpResponse.json(buildInvoiceWithLinesDto({ status: 'draft', invoice_number: null })),
      ),
    )

    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.canIssue).toBe(true)
    })
  })

  it('hides the action for an already-issued invoice', async () => {
    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.canIssue).toBe(false)
    })
  })
})
