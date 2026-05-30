import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toQuoteId } from './ids'
import { useChangeQuoteStatus, useConvertQuote, useCreateQuote } from './mutations'

describe('useCreateQuote', () => {
  it('posts and returns the mapped quote with lines', async () => {
    const { result } = renderHookWithProviders(() => useCreateQuote())
    result.current.mutate({
      client_id: 5,
      line_items: [
        { description: '作業', quantity: 1, unit_price_cents: 100000, tax_rate_bps: 1000 },
      ],
      valid_until: null,
      notes: null,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(1)
    expect(result.current.data?.status).toBe('draft')
    expect(result.current.data?.total_cents).toBe(110000)
  })

  it('surfaces an AppError on a 422 response', async () => {
    server.use(
      http.post(
        '/admin/quotes',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/validation-failed',
              title: 'Validation Failed',
              status: 422,
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateQuote())
    result.current.mutate({ client_id: 5, line_items: [], valid_until: null, notes: null })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('validation-failed')
  })
})

describe('useChangeQuoteStatus', () => {
  it('patches and returns the updated status', async () => {
    const { result } = renderHookWithProviders(() => useChangeQuoteStatus())
    result.current.mutate({ id: toQuoteId(1), status: 'sent' })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.status).toBe('sent')
  })
})

describe('useConvertQuote', () => {
  it('converts the quote and returns the new invoice', async () => {
    const { result } = renderHookWithProviders(() => useConvertQuote())
    result.current.mutate(toQuoteId(1))

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.id).toBe(10)
  })
})
