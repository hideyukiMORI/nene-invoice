import { useEffect, useRef, useState } from 'react'

/** Action carried by the global `kbd:list` event from the dispatcher. */
export type RowCursorAction = 'down' | 'up' | 'open'

/** Custom event name the dispatcher emits for j / k / o / Enter (spec §1-4). */
export const KBD_LIST_EVENT = 'kbd:list'

/**
 * List row cursor (Issue #257, 第4層). A list opts in by calling this with its
 * row count and an open handler; the global {@link KeyboardShortcuts} dispatcher
 * emits `kbd:list` for j/k/o/Enter, and the cursored row is highlighted with
 * `.is-cursor` (the caller renders it). Returns the active index, or -1 for none.
 */
export function useRowCursor(count: number, onOpen: (index: number) => void): number {
  const [cursor, setCursor] = useState(-1)
  const cursorRef = useRef(-1)
  const onOpenRef = useRef(onOpen)

  useEffect(() => {
    cursorRef.current = cursor
  }, [cursor])
  useEffect(() => {
    onOpenRef.current = onOpen
  }, [onOpen])

  // Keep the cursor in range when the row set shrinks (e.g. after filtering).
  // Adjusting state during render is the React-recommended pattern here.
  if (cursor >= count) {
    setCursor(count - 1)
  }

  useEffect(() => {
    const handler = (event: Event): void => {
      const action = (event as CustomEvent<{ action: RowCursorAction }>).detail.action
      if (action === 'down') {
        setCursor((c) => Math.min(count - 1, c + 1))
      } else if (action === 'up') {
        setCursor((c) => (c <= 0 ? 0 : c - 1))
      } else if (cursorRef.current >= 0) {
        onOpenRef.current(cursorRef.current)
      }
    }
    document.addEventListener(KBD_LIST_EVENT, handler)
    return () => {
      document.removeEventListener(KBD_LIST_EVENT, handler)
    }
  }, [count])

  // Keep the cursored row visible.
  useEffect(() => {
    if (cursor < 0) return
    const el = document.querySelector(`[data-kbd-row="${String(cursor)}"]`)
    if (el instanceof HTMLElement && typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ block: 'nearest' })
    }
  }, [cursor])

  return cursor
}
