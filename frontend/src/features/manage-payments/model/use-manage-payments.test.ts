import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toInvoiceId } from '@/entities/invoice'
import { server } from '@tests/msw/server'
import { buildInvoiceWithLinesDto } from '@tests/factories/invoice'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useManagePayments } from './use-manage-payments'

describe('useManagePayments (feature)', () => {
  it('is visible and recordable for an issued invoice', async () => {
    const { result } = renderHookWithProviders(() => useManagePayments(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.visible).toBe(true)
    })

    expect(result.current.canRecord).toBe(true)
    expect(result.current.totalPaidCents).toBe(0)
  })

  it('is hidden for a draft invoice', async () => {
    server.use(
      http.get('/admin/invoices/:id', () =>
        HttpResponse.json(buildInvoiceWithLinesDto({ status: 'draft', invoice_number: null })),
      ),
    )

    const { result } = renderHookWithProviders(() => useManagePayments(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.paymentsLoading).toBe(false)
    })

    expect(result.current.visible).toBe(false)
    expect(result.current.canRecord).toBe(false)
  })

  it('opens the confirm dialog on valid submission and closes on cancel', async () => {
    const { result } = renderHookWithProviders(() => useManagePayments(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.canRecord).toBe(true)
    })

    act(() => {
      result.current.form.setValue('amount_cents', 10000)
      result.current.onSubmit({ preventDefault: () => {} } as unknown as React.SyntheticEvent)
    })

    await waitFor(() => {
      expect(result.current.confirming).toBe(true)
    })

    act(() => {
      result.current.onCancel()
    })

    expect(result.current.confirming).toBe(false)
  })
})
