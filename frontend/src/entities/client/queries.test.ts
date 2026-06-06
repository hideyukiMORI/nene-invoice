import { act, waitFor } from '@testing-library/react'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useClientList } from './queries'

/**
 * The client picker drives a per-keystroke server search, so the query key
 * changes on every character. Without `placeholderData: keepPreviousData` the
 * status flips to 'pending' on each change, which disabled the picker input
 * mid-typing (#368). This pins that the status stays settled across key changes.
 */
function useProbe() {
  const [q, setQ] = useState<string | null>(null)
  const query = useClientList({
    limit: 100,
    offset: 0,
    filters: { q },
    sort: { field: null, order: 'asc' },
  })
  return { setQ, query }
}

describe('useClientList', () => {
  it('does not return to isPending when the search query changes (#368)', async () => {
    const { result } = renderHookWithProviders(() => useProbe())

    await waitFor(() => {
      expect(result.current.query.isPending).toBe(false)
    })

    // Changing the query key (a keystroke) must not flip back to pending —
    // keepPreviousData holds the prior page while the new one loads.
    act(() => {
      result.current.setQ('c')
    })
    expect(result.current.query.isPending).toBe(false)

    act(() => {
      result.current.setQ('cr')
    })
    expect(result.current.query.isPending).toBe(false)
  })
})
