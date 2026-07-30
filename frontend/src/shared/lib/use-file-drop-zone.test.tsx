import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useFileDropZone } from './use-file-drop-zone'

function DropHarness({ onFile }: { onFile: (file: File) => void }) {
  const { ref, dragging } = useFileDropZone(onFile)
  return (
    <label ref={ref} aria-label="drop zone" className={dragging ? 'dropzone is-drag' : 'dropzone'}>
      <input type="file" aria-label="file" />
    </label>
  )
}

const zone = (): HTMLElement => screen.getByLabelText('drop zone')

describe('useFileDropZone', () => {
  it('flags dragging while a drag hovers the zone and clears it on leave', () => {
    render(<DropHarness onFile={vi.fn()} />)
    expect(zone()).not.toHaveClass('is-drag')

    fireEvent.dragEnter(zone())
    expect(zone()).toHaveClass('is-drag')

    fireEvent.dragLeave(zone())
    expect(zone()).not.toHaveClass('is-drag')
  })

  it('reports the dropped file and clears the dragging state', () => {
    const onFile = vi.fn()
    render(<DropHarness onFile={onFile} />)
    const file = new File(['date,amount\n'], 'statement.csv', { type: 'text/csv' })

    fireEvent.dragEnter(zone())
    fireEvent.drop(zone(), { dataTransfer: { files: [file] } })

    expect(onFile).toHaveBeenCalledWith(file)
    expect(zone()).not.toHaveClass('is-drag')
  })

  it('ignores a drop that carries no file', () => {
    const onFile = vi.fn()
    render(<DropHarness onFile={onFile} />)

    fireEvent.drop(zone(), { dataTransfer: { files: [] } })

    expect(onFile).not.toHaveBeenCalled()
  })

  it('calls the latest callback after a re-render (no stale closure)', () => {
    const first = vi.fn()
    const second = vi.fn()
    const { rerender } = render(<DropHarness onFile={first} />)
    rerender(<DropHarness onFile={second} />)
    const file = new File(['x'], 'x.csv', { type: 'text/csv' })

    fireEvent.drop(zone(), { dataTransfer: { files: [file] } })

    expect(second).toHaveBeenCalledWith(file)
    expect(first).not.toHaveBeenCalled()
  })
})
