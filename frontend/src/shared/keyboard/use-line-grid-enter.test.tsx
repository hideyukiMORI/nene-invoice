import { fireEvent, render } from '@testing-library/react'
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
    const { getByLabelText } = render(<GridHarness />)
    const a0 = getByLabelText('a0')
    a0.focus()

    fireEvent.keyDown(a0, { key: 'Enter' })

    expect(getByLabelText('b0')).toHaveFocus()
  })

  it('adds a row and focuses its first cell from the last cell', () => {
    const { getByLabelText, queryByLabelText } = render(<GridHarness />)
    const b0 = getByLabelText('b0')
    b0.focus()

    fireEvent.keyDown(b0, { key: 'Enter' })

    expect(queryByLabelText('a1')).not.toBeNull()
    expect(getByLabelText('a1')).toHaveFocus()
  })
})
