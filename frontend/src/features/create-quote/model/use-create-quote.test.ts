import { act, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useCreateQuote } from './use-create-quote'

describe('useCreateQuote', () => {
  it('loads selectable clients from the API', async () => {
    const { result } = renderHookWithProviders(() => useCreateQuote())
    expect(result.current.clientsLoading).toBe(true)

    await waitFor(() => {
      expect(result.current.clientsLoading).toBe(false)
    })
    expect(result.current.clients).toHaveLength(1)
  })

  it('starts with one line and appends more via addLine', () => {
    const { result } = renderHookWithProviders(() => useCreateQuote())
    expect(result.current.lines.fields).toHaveLength(1)

    act(() => {
      result.current.addLine()
    })
    expect(result.current.lines.fields).toHaveLength(2)
  })

  it('has no error message initially', () => {
    const { result } = renderHookWithProviders(() => useCreateQuote())
    expect(result.current.errorMessage).toBeNull()
    expect(result.current.isPending).toBe(false)
  })
})
