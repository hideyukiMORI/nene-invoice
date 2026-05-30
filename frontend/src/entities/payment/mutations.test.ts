import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useRecordPayment } from './mutations'

describe('useRecordPayment', () => {
  it('posts a payment and returns the mapped result', async () => {
    server.use(
      http.post('/admin/invoices/:id/payments', () =>
        HttpResponse.json(
          {
            payment: {
              id: 3,
              organization_id: 1,
              invoice_id: 7,
              amount_cents: 50000,
              paid_at: '2026-05-30',
              method: 'bank_transfer',
              note: null,
            },
            total_paid_cents: 50000,
          },
          { status: 201 },
        ),
      ),
    )

    const { result } = renderHookWithProviders(() => useRecordPayment())
    result.current.mutate({
      invoice_id: 7,
      amount_cents: 50000,
      method: 'bank_transfer',
      note: null,
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.payment.id).toBe(3)
    expect(result.current.data?.payment.method).toBe('bank_transfer')
    expect(result.current.data?.total_paid_cents).toBe(50000)
  })

  it('surfaces an AppError when the amount exceeds the outstanding balance', async () => {
    server.use(
      http.post(
        '/admin/invoices/:id/payments',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/payment-exceeds-balance',
              title: 'Unprocessable Entity',
              status: 422,
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useRecordPayment())
    result.current.mutate({ invoice_id: 7, amount_cents: 999999, method: null, note: null })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('payment-exceeds-balance')
  })
})
