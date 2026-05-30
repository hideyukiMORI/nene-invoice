import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toQuoteId } from '@/entities/quote'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useViewQuote } from './use-view-quote'

const quoteDto = (status: string) => ({
  id: 1,
  organization_id: 1,
  client_id: 5,
  quote_number: 'EST-2026-001',
  status,
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  line_items: [],
})

describe('useViewQuote', () => {
  it('starts loading then exposes the quote', async () => {
    const { result } = renderHookWithProviders(() => useViewQuote(toQuoteId(1)))
    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })
  })

  it('derives capability flags from a draft quote', async () => {
    server.use(http.get('/admin/quotes/:id', () => HttpResponse.json(quoteDto('draft'))))

    const { result } = renderHookWithProviders(() => useViewQuote(toQuoteId(1)))
    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.canSend).toBe(true)
      expect(result.current.canAccept).toBe(false)
      expect(result.current.canConvert).toBe(false)
    }
  })

  it('allows conversion only once a quote is accepted', async () => {
    server.use(http.get('/admin/quotes/:id', () => HttpResponse.json(quoteDto('accepted'))))

    const { result } = renderHookWithProviders(() => useViewQuote(toQuoteId(1)))
    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.canConvert).toBe(true)
      expect(result.current.canSend).toBe(false)
    }
  })

  it('surfaces an action error when a status change fails', async () => {
    server.use(
      http.get('/admin/quotes/:id', () => HttpResponse.json(quoteDto('draft'))),
      http.patch('/admin/quotes/:id', () => new HttpResponse(null, { status: 422 })),
    )

    const { result } = renderHookWithProviders(() => useViewQuote(toQuoteId(1)))
    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      act(() => {
        result.current.changeStatus('sent')
      })
    }

    await waitFor(() => {
      if (result.current.kind === 'ready') {
        expect(result.current.actionError).not.toBeNull()
      }
    })
  })
})
