import { act, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useCreateInvoice } from './use-create-invoice'

describe('useCreateInvoice (feature)', () => {
  it('loads the client list for the picker and starts with one line', async () => {
    const { result } = renderHookWithProviders(() => useCreateInvoice())

    await waitFor(() => {
      expect(result.current.clientsLoading).toBe(false)
    })

    expect(result.current.clients).toHaveLength(1)
    expect(result.current.clients[0]?.name).toBe('得意先ABC')
    expect(result.current.lines.fields).toHaveLength(1)
  })

  it('appends and removes line items', async () => {
    const { result } = renderHookWithProviders(() => useCreateInvoice())

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
})
