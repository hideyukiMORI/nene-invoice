import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { KeyboardShortcuts } from './KeyboardShortcuts'
import { openShortcutsOverlay } from './overlay-control'
import { useRowCursor } from './use-row-cursor'

// The probe exposes the current path through a queryable role rather than a test
// id, so assertions use the same accessible surface a user would.
function LocationProbe() {
  return (
    <div role="status" aria-label="location">
      {useLocation().pathname}
    </div>
  )
}

const locationProbe = () => screen.getByRole('status', { name: 'location' })

function ListHarness({ onOpen }: { onOpen: (index: number) => void }) {
  const cursor = useRowCursor(3, onOpen)
  return (
    <ul>
      {[0, 1, 2].map((i) => (
        <li
          key={i}
          data-kbd-row={i}
          // aria-current is the accessible expression of "this row holds the
          // cursor" — it replaces reaching into the DOM for the .is-cursor class.
          aria-current={cursor === i ? true : undefined}
          className={cursor === i ? 'is-cursor' : undefined}
        >
          row{i}
        </li>
      ))}
    </ul>
  )
}

describe('KeyboardShortcuts', () => {
  it('navigates with the g-prefix sequence (g then i → invoices)', async () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 'i' })

    await waitFor(() => {
      expect(locationProbe()).toHaveTextContent('/invoices')
    })
  })

  it('navigates to items (g m) and templates (g t), and n opens new item', async () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 'm' })
    await waitFor(() => {
      expect(locationProbe()).toHaveTextContent('/items')
    })

    fireEvent.keyDown(document.body, { key: 'n' })
    await waitFor(() => {
      expect(locationProbe()).toHaveTextContent('/items/new')
    })

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 't' })
    await waitFor(() => {
      expect(locationProbe()).toHaveTextContent('/templates')
    })
  })

  it('returns to the parent list with u from a detail view', () => {
    render(
      <MemoryRouter initialEntries={['/invoices/42']}>
        <KeyboardShortcuts />
        <LocationProbe />
      </MemoryRouter>,
    )

    fireEvent.keyDown(document.body, { key: 'u' })
    expect(locationProbe()).toHaveTextContent('/invoices')
  })

  it('returns to the parent list with u from an edit form when no field is focused (#374)', () => {
    render(
      <MemoryRouter initialEntries={['/clients/42/edit']}>
        <KeyboardShortcuts />
        <LocationProbe />
      </MemoryRouter>,
    )

    fireEvent.keyDown(document.body, { key: 'u' })
    expect(locationProbe()).toHaveTextContent('/clients')
  })

  it('does not fire u while a form field is focused (typing wins) (#374)', () => {
    render(
      <MemoryRouter initialEntries={['/clients/42/edit']}>
        <KeyboardShortcuts />
        <LocationProbe />
        <input aria-label="field" />
      </MemoryRouter>,
    )
    const field = screen.getByRole('textbox')
    field.focus()

    fireEvent.keyDown(field, { key: 'u' })
    expect(locationProbe()).toHaveTextContent('/clients/42/edit')
  })

  it('blurs the search field on Esc so j/k work again (#362)', () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input data-kbd="search" aria-label="search" />
      </>,
    )
    const search = screen.getByRole('textbox')
    search.focus()
    expect(search).toHaveFocus()

    fireEvent.keyDown(search, { key: 'Escape' })
    expect(search).not.toHaveFocus()
  })

  it('blurs any focused form field on Esc, not just search (#364)', () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input aria-label="plain" />
        <textarea aria-label="notes" />
      </>,
    )
    const plain = screen.getByRole('textbox', { name: 'plain' })
    plain.focus()
    fireEvent.keyDown(plain, { key: 'Escape' })
    expect(plain).not.toHaveFocus()

    const notes = screen.getByRole('textbox', { name: 'notes' })
    notes.focus()
    fireEvent.keyDown(notes, { key: 'Escape' })
    expect(notes).not.toHaveFocus()
  })

  it('keeps focus on Esc while composing in the search field (IME cancel) (#362)', () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input data-kbd="search" aria-label="search" />
      </>,
    )
    const search = screen.getByRole('textbox')
    search.focus()

    fireEvent.keyDown(search, { key: 'Escape', isComposing: true })
    expect(search).toHaveFocus()
  })

  it('opens the command palette on Ctrl/⌘+K and navigates with j + Enter (#370)', async () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

    fireEvent.keyDown(document.body, { key: 'k', ctrlKey: true })
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    // cursor starts on the first command (dashboard); j → quotes, Enter goes.
    fireEvent.keyDown(document.body, { key: 'j' })
    fireEvent.keyDown(document.body, { key: 'Enter' })
    await waitFor(() => {
      expect(locationProbe()).toHaveTextContent('/quotes')
    })
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('closes the command palette on Esc (#370)', () => {
    renderWithProviders(<KeyboardShortcuts />)
    fireEvent.keyDown(document.body, { key: 'k', metaKey: true })
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(document.body, { key: 'Escape' })
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('groups palette commands with non-selectable headers (design 案A, #370)', () => {
    renderWithProviders(<KeyboardShortcuts />)
    fireEvent.keyDown(document.body, { key: 'k', metaKey: true })

    // Group headers are presentation (not options); 10 navigable options remain.
    const list = screen.getByRole('listbox')
    expect(screen.getAllByRole('option')).toHaveLength(10)
    expect(within(list).getAllByRole('presentation')).toHaveLength(3)
    // Each option shows its key combo as separate caps (g then d for dashboard).
    const firstOption = screen.getAllByRole('option')[0]
    expect(firstOption).toBeDefined()
    expect(within(firstOption as HTMLElement).getByText('g')).toBeInTheDocument()
    expect(within(firstOption as HTMLElement).getByText('d')).toBeInTheDocument()
  })

  it('opens the cheat-sheet via openShortcutsOverlay()', () => {
    renderWithProviders(<KeyboardShortcuts />)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

    act(() => {
      openShortcutsOverlay()
    })
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('opens the cheat-sheet on ? and closes it on Esc', () => {
    renderWithProviders(<KeyboardShortcuts />)

    fireEvent.keyDown(document.body, { key: '?' })
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(document.body, { key: 'Escape' })
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('ignores single keys while the IME is composing', () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g', isComposing: true })
    fireEvent.keyDown(document.body, { key: 'i' })

    expect(locationProbe()).toHaveTextContent('/')
  })

  it('ignores single keys when focus is in an editable field', () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
        <input aria-label="field" />
      </>,
    )
    const input = screen.getByRole('textbox')

    fireEvent.keyDown(input, { key: 'g' })
    fireEvent.keyDown(input, { key: 'i' })

    expect(locationProbe()).toHaveTextContent('/')
  })

  it('focuses the list search box on /', () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input data-kbd="search" aria-label="search" />
      </>,
    )

    fireEvent.keyDown(document.body, { key: '/' })

    expect(screen.getByRole('textbox')).toHaveFocus()
  })

  it('opens the contextual new form on n (invoices → /invoices/new)', async () => {
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'g' })
    fireEvent.keyDown(document.body, { key: 'i' })
    await waitFor(() => {
      expect(locationProbe()).toHaveTextContent('/invoices')
    })

    fireEvent.keyDown(document.body, { key: 'n' })
    await waitFor(() => {
      expect(locationProbe()).toHaveTextContent('/invoices/new')
    })
  })

  it('moves the row cursor with j/k and opens the cursored row with o', () => {
    const onOpen = vi.fn()
    renderWithProviders(
      <>
        <KeyboardShortcuts />
        <ListHarness onOpen={onOpen} />
      </>,
    )

    fireEvent.keyDown(document.body, { key: 'j' })
    expect(screen.getByRole('listitem', { current: true })).toHaveTextContent('row0')
    fireEvent.keyDown(document.body, { key: 'j' })
    expect(screen.getByRole('listitem', { current: true })).toHaveTextContent('row1')
    fireEvent.keyDown(document.body, { key: 'k' })
    expect(screen.getByRole('listitem', { current: true })).toHaveTextContent('row0')

    fireEvent.keyDown(document.body, { key: 'o' })
    expect(onOpen).toHaveBeenCalledWith(0)
  })

  it('submits the surrounding form on Ctrl/Cmd+Enter, even from a field', () => {
    const onSubmit = vi.fn((e: { preventDefault: () => void }) => {
      e.preventDefault()
    })
    renderWithProviders(
      <form onSubmit={onSubmit}>
        <input aria-label="field" />
        <button type="submit">submit</button>
      </form>,
    )
    // KeyboardShortcuts is also needed; render it alongside via a second mount.
    renderWithProviders(<KeyboardShortcuts />)
    const input = screen.getByRole('textbox')

    fireEvent.keyDown(input, { key: 'Enter', ctrlKey: true })

    expect(onSubmit).toHaveBeenCalledOnce()
  })
})
