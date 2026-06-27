import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import type { SyntheticEvent } from 'react'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { buildRecurringInvoiceWithLinesDto } from '@tests/factories/recurring-invoice'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useCreateRecurringInvoice } from './use-create-recurring-invoice'

const fakeSubmitEvent = { preventDefault: () => {} } as unknown as SyntheticEvent

describe('useCreateRecurringInvoice (feature)', () => {
  it('loads the client list for the picker and starts with one line', async () => {
    const { result } = renderHookWithProviders(() => useCreateRecurringInvoice())

    await waitFor(() => {
      expect(result.current.clientsLoading).toBe(false)
    })

    expect(result.current.clients).toHaveLength(1)
    expect(result.current.clients[0]?.name).toBe('得意先ABC')
    expect(result.current.lines.fields).toHaveLength(1)
  })

  it('appends and removes line items', async () => {
    const { result } = renderHookWithProviders(() => useCreateRecurringInvoice())

    await waitFor(() => {
      expect(result.current.clientsLoading).toBe(false)
    })

    act(() => {
      result.current.addLine()
    })
    expect(result.current.lines.fields).toHaveLength(2)

    act(() => {
      result.current.lines.remove(1)
    })
    expect(result.current.lines.fields).toHaveLength(1)
  })

  it('submits the schedule and posts the mapped payload (happy path)', async () => {
    let body: unknown = null
    server.use(
      http.post('/admin/recurring-invoices', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json(buildRecurringInvoiceWithLinesDto(), { status: 201 })
      }),
    )

    const { result } = renderHookWithProviders(() => useCreateRecurringInvoice())
    await waitFor(() => {
      expect(result.current.clientsLoading).toBe(false)
    })

    act(() => {
      result.current.form.setValue('client_id', 5)
      result.current.form.setValue('name', '月次顧問料')
      result.current.form.setValue('frequency', 'quarterly')
      result.current.form.setValue('first_run_on', '2026-07-01')
      result.current.form.setValue('line_items.0.description', 'Consulting')
      result.current.form.setValue('line_items.0.unit_price_cents', 100000)
      result.current.onSubmit(fakeSubmitEvent)
    })

    await waitFor(() => {
      expect(body).not.toBeNull()
    })
    expect(body).toMatchObject({
      client_id: 5,
      name: '月次顧問料',
      frequency: 'quarterly',
      first_run_on: '2026-07-01',
      is_active: true,
    })
  })

  it('does not post when required fields are missing (validation)', async () => {
    let posted = false
    server.use(
      http.post('/admin/recurring-invoices', () => {
        posted = true
        return HttpResponse.json(buildRecurringInvoiceWithLinesDto(), { status: 201 })
      }),
    )

    const { result } = renderHookWithProviders(() => useCreateRecurringInvoice())
    await waitFor(() => {
      expect(result.current.clientsLoading).toBe(false)
    })

    act(() => {
      result.current.onSubmit(fakeSubmitEvent)
    })

    await waitFor(() => {
      expect(result.current.form.formState.errors.client_id).toBeDefined()
    })
    expect(result.current.form.formState.errors.name).toBeDefined()
    expect(posted).toBe(false)
  })
})
