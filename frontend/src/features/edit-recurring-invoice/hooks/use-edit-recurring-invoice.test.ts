import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { buildRecurringInvoiceWithLinesDto } from '@tests/factories/recurring-invoice'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { toRecurringInvoiceId } from '@/entities/recurring-invoice'
import { useEditRecurringInvoice } from './use-edit-recurring-invoice'

describe('useEditRecurringInvoice', () => {
  it('loads and prefills the form from the schedule', async () => {
    server.use(
      http.get('/admin/recurring-invoices/:id', () =>
        HttpResponse.json(
          buildRecurringInvoiceWithLinesDto({ name: '月次顧問料', frequency: 'quarterly' }),
        ),
      ),
    )

    const { result } = renderHookWithProviders(() =>
      useEditRecurringInvoice(toRecurringInvoiceId(7)),
    )

    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      const values = result.current.form.getValues()
      expect(values.name).toBe('月次顧問料')
      expect(values.frequency).toBe('quarterly')
      expect(values.client_id).toBe(5)
      expect(values.line_items).toHaveLength(1)
    }
  })

  it('exposes an error state on a 5xx response', async () => {
    server.use(
      http.get('/admin/recurring-invoices/:id', () => new HttpResponse(null, { status: 500 })),
    )

    const { result } = renderHookWithProviders(() =>
      useEditRecurringInvoice(toRecurringInvoiceId(7)),
    )

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
