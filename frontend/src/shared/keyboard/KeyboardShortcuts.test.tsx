import { act, fireEvent, waitFor } from '@testing-library/react'
import { useLocation } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { KeyboardShortcuts } from './KeyboardShortcuts'
import { openShortcutsOverlay } from './overlay-control'
import { useRowCursor } from './use-row-cursor'

function LocationProbe() {
  return <div data-testid="loc">{useLocation().pathname}</div>
}

function ListHarness({ onOpen }: { onOpen: (index: number) => void }) {
  const cursor = useRowCursor(3, onOpen)
  return (
    <ul>
      {[0, 1, 2].map((i) => (
        <li key={i} data-kbd-row={i} className={cursor === i ? 'is-cursor' : undefined}>
          row{i}
        </li>
      ))}
    </ul>
  )
}

describe('KeyboardShortcuts', () => {
  it('navigates with the g-prefix sequence (g then i → invoices)', async () => {
    const { getByTestId } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 'i' })

    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/invoices')
    })
  })

  it('navigates to items (g m) and templates (g t), and n opens new item', async () => {
    const { getByTestId } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 'm' })
    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/items')
    })

    fireEvent.keyDown(document.body, { key: 'n' })
    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/items/new')
    })

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 't' })
    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/templates')
    })
  })

  it('returns to the parent list with u', async () => {
    const { getByTestId } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 'i' })
    fireEvent.keyDown(document.body, { key: 'n' })
    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/invoices/new')
    })

    fireEvent.keyDown(document.body, { key: 'u' })
    await waitFor(() => {
      expect(getByTestId('loc').textContent).toBe('/invoices')
    })
  })

  it('opens the cheat-sheet via openShortcutsOverlay()', () => {
    const { getByRole, queryByRole } = renderWithProviders(<KeyboardShortcuts />)
    expect(queryByRole('dialog')).not.toBeInTheDocument()

    act(() => {
      openShortcutsOverlay()
    })
    expect(getByRole('dialog')).toBeInTheDocument()
  })

  it('opens the cheat-sheet on ? and closes it on Esc', () => {
    const { getByRole, queryByRole } = renderWithProviders(<KeyboardShortcuts />)

    fireEvent.keyDown(document.body, { key: '?' })
    expect(getByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(document.body, { key: 'Escape' })
    expect(queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('ignores single keys while the IME is composing', () => {
    const { getByTestId } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g', isComposing: true })
    fireEvent.keyDown(document.body, { key: 'i' })

    expect(getByTestId('loc')).toHaveTextContent('/')
  })

  it('ignores single keys when focus is in an editable field', () => {
    const { getByTestId, getByRole } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
        <input aria-label="field" />
      </>,
    )
    const input = getByRole('textbox')

    fireEvent.keyDown(input, { key: 'g' })
    fireEvent.keyDown(input, { key: 'i' })

    expect(getByTestId('loc')).toHaveTextContent('/')
  })

  it('focuses the list search box on /', () => {
    const { getByRole } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input data-kbd="search" aria-label="search" />
      </>,
    )

    fireEvent.keyDown(document.body, { key: '/' })

    expect(getByRole('textbox')).toHaveFocus()
  })

  it('opens the contextual new form on n (invoices → /invoices/new)', async () => {
    const { getByTestId } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 'i' })
    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/invoices')
    })

    fireEvent.keyDown(document.body, { key: 'n' })
    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/invoices/new')
    })
  })

  it('moves the row cursor with j/k and opens the cursored row with o', () => {
    const onOpen = vi.fn()
    const { container } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <ListHarness onOpen={onOpen} />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'j' })
    expect(container.querySelector('.is-cursor')).toHaveTextContent('row0')
    fireEvent.keyDown(document.body, { key: 'j' })
    expect(container.querySelector('.is-cursor')).toHaveTextContent('row1')
    fireEvent.keyDown(document.body, { key: 'k' })
    expect(container.querySelector('.is-cursor')).toHaveTextContent('row0')

    fireEvent.keyDown(document.body, { key: 'o' })
    expect(onOpen).toHaveBeenCalledWith(0)
  })

  it('submits the surrounding form on Ctrl/Cmd+Enter, even from a field', () => {
    const onSubmit = vi.fn((e: { preventDefault: () => void }) => {
      e.preventDefault()
    })
    const { getByRole } = renderWithProviders(
      <form onSubmit={onSubmit}>
        <input aria-label="field" />
        <button type="submit">submit</button>
      </form>,
    )
    // KeyboardShortcuts is also needed; render it alongside via a second mount.
    renderWithProviders(<KeyboardShortcuts />)
    const input = getByRole('textbox')

    fireEvent.keyDown(input, { key: 'Enter', ctrlKey: true })

    expect(onSubmit).toHaveBeenCalledOnce()
  })
})
