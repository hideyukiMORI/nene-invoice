import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import type { SyntheticEvent } from 'react'
import { describe, expect, it } from 'vitest'
import { toTemplateId } from '@/entities/template'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useTemplateForm, type TemplateFormState } from './use-template-form'

const fakeSubmitEvent = { preventDefault: () => {} } as unknown as SyntheticEvent

/** Narrows the discriminated state; fails the test if it is not `ready`. */
function expectReady(state: TemplateFormState): Extract<TemplateFormState, { kind: 'ready' }> {
  if (state.kind !== 'ready') throw new Error(`expected ready, got ${state.kind}`)
  return state
}

describe('useTemplateForm — create mode', () => {
  it('starts ready with one blank line and no error', () => {
    const { result } = renderHookWithProviders(() => useTemplateForm())
    const ready = expectReady(result.current)
    expect(ready.isEdit).toBe(false)
    expect(ready.errorMessage).toBeNull()
    expect(ready.isPending).toBe(false)
    expect(ready.lines.fields).toHaveLength(1)
  })

  it('appends a line via addLine', () => {
    const { result } = renderHookWithProviders(() => useTemplateForm())

    act(() => {
      expectReady(result.current).addLine()
    })

    expect(expectReady(result.current).lines.fields).toHaveLength(2)
  })

  it('drops blank lines, trims descriptions and maps empty notes to null on submit', async () => {
    let captured: unknown
    server.use(
      http.post('/admin/templates', async ({ request }) => {
        captured = await request.json()
        return HttpResponse.json(
          { id: 1, name: 'Std', notes: null, line_items: [] },
          { status: 201 },
        )
      }),
    )

    const { result } = renderHookWithProviders(() => useTemplateForm())
    const ready = expectReady(result.current)

    act(() => {
      ready.form.setValue('name', 'Std')
      ready.form.setValue('notes', '')
      ready.form.setValue('line_items', [
        { description: '  Widget  ', quantity: 2, unit_price_cents: 1000, tax_rate_bps: 1000 },
        { description: '   ', quantity: 1, unit_price_cents: 0, tax_rate_bps: 1000 },
      ])
    })

    act(() => {
      ready.onSubmit(fakeSubmitEvent)
    })

    await waitFor(() => {
      expect(captured).toBeDefined()
    })

    expect(captured).toEqual({
      name: 'Std',
      notes: null,
      line_items: [
        { description: 'Widget', quantity: 2, unit_price_cents: 1000, tax_rate_bps: 1000 },
      ],
    })
  })

  it('surfaces an error message when creation fails', async () => {
    server.use(
      http.post(
        '/admin/templates',
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

    const { result } = renderHookWithProviders(() => useTemplateForm())
    const ready = expectReady(result.current)

    act(() => {
      ready.form.setValue('name', 'Std')
      ready.onSubmit(fakeSubmitEvent)
    })

    await waitFor(() => {
      expect(expectReady(result.current).errorMessage).not.toBeNull()
    })
  })
})

describe('useTemplateForm — edit mode', () => {
  it('is loading while the template query is pending', () => {
    server.use(
      http.get('/admin/templates/:id', () =>
        HttpResponse.json({ id: 7, name: 'Loaded', notes: null, line_items: [] }),
      ),
    )
    const { result } = renderHookWithProviders(() => useTemplateForm(toTemplateId(7)))
    expect(result.current.kind).toBe('loading')
  })

  it('exposes an error state with a retry when the query fails', async () => {
    server.use(http.get('/admin/templates/:id', () => new HttpResponse(null, { status: 500 })))
    const { result } = renderHookWithProviders(() => useTemplateForm(toTemplateId(7)))

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
    const state = result.current
    if (state.kind !== 'error') throw new Error(`expected error, got ${state.kind}`)
    expect(typeof state.retry).toBe('function')
  })

  it('prefills the form and preserves an 8% rate once loaded', async () => {
    server.use(
      http.get('/admin/templates/:id', () =>
        HttpResponse.json({
          id: 7,
          name: 'Loaded',
          notes: 'memo',
          line_items: [{ description: 'X', quantity: 3, unit_price_cents: 500, tax_rate_bps: 800 }],
        }),
      ),
    )
    const { result } = renderHookWithProviders(() => useTemplateForm(toTemplateId(7)))

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })
    const ready = expectReady(result.current)

    expect(ready.isEdit).toBe(true)
    expect(ready.form.getValues('name')).toBe('Loaded')
    expect(ready.form.getValues('notes')).toBe('memo')
    expect(ready.form.getValues('line_items')[0]?.tax_rate_bps).toBe(800)
  })
})
