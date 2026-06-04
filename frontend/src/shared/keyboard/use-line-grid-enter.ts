import { useEffect, useRef } from 'react'

/**
 * Line-item grid Enter navigation (Issue #257, spec §1-4 — the primary
 * data-entry shortcut). Enter moves to the next cell; at the last cell it adds a
 * new row and focuses its first cell, so the hand never leaves the grid. This is
 * form-local (the global dispatcher's IME guard skips keys inside fields).
 *
 * Attach the returned `gridRef` to the grid container. Only `input` and `select`
 * cells participate; buttons (e.g. remove) keep their native Enter. The keydown
 * listener is attached natively so the container stays a plain, non-interactive
 * element (the focusable cells carry the interaction).
 */
export function useLineGridEnter(
  rowCount: number,
  addRow: () => void,
): { gridRef: React.RefObject<HTMLDivElement | null> } {
  const gridRef = useRef<HTMLDivElement | null>(null)
  const focusIndexRef = useRef<number | null>(null)
  const addRowRef = useRef(addRow)

  useEffect(() => {
    addRowRef.current = addRow
  }, [addRow])

  useEffect(() => {
    const container = gridRef.current
    if (container === null) return

    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key !== 'Enter' || !(event.target instanceof HTMLElement)) return
      const fields = Array.from(container.querySelectorAll<HTMLElement>('input, select'))
      const index = fields.indexOf(event.target)
      if (index === -1) return

      // Intercept the browser's implicit form submit on Enter inside a cell.
      event.preventDefault()
      const next = fields[index + 1]
      if (next !== undefined) {
        next.focus()
      } else {
        // Last cell: the new row's first field will append at the current length.
        focusIndexRef.current = fields.length
        addRowRef.current()
      }
    }

    container.addEventListener('keydown', onKeyDown)
    return () => {
      container.removeEventListener('keydown', onKeyDown)
    }
  }, [])

  // After a row is added, focus the first cell of the new (last) row.
  useEffect(() => {
    const target = focusIndexRef.current
    if (target === null) return
    focusIndexRef.current = null
    const fields = gridRef.current?.querySelectorAll<HTMLElement>('input, select')
    fields?.[target]?.focus()
  }, [rowCount])

  return { gridRef }
}
