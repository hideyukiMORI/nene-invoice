import { fireEvent, render, screen } from '@testing-library/react'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { useLineGridEnter } from './use-line-grid-enter'

function GridHarness() {
  const [rows, setRows] = useState([0])
  const { gridRef } = useLineGridEnter(rows.length, () => {
    setRows((r) => [...r, r.length])
  })
  return (
    <div className="line-grid" ref={gridRef}>
      {rows.map((r) => (
        <div key={r}>
          <input aria-label={`a${String(r)}`} />
          <input aria-label={`b${String(r)}`} />
        </div>
      ))}
    </div>
  )
}

describe('useLineGridEnter', () => {
  it('moves focus to the next cell on Enter', () => {
    render(<GridHarness />)
    const a0 = screen.getByLabelText('a0')
    a0.focus()

    fireEvent.keyDown(a0, { key: 'Enter' })

    expect(screen.getByLabelText('b0')).toHaveFocus()
  })

  it('adds a row and focuses its first cell from the last cell', () => {
    render(<GridHarness />)
    const b0 = screen.getByLabelText('b0')
    b0.focus()

    fireEvent.keyDown(b0, { key: 'Enter' })

    const a1 = screen.getByLabelText('a1')
    expect(a1).toBeInTheDocument()
    expect(a1).toHaveFocus()
  })
})
