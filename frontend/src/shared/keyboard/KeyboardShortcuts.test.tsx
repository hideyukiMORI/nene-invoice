import { act, fireEvent, render, waitFor } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
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

  it('returns to the parent list with u from a detail view', () => {
    const { getByTestId } = render(
      <MemoryRouter initialEntries={['/invoices/42']}>
        <KeyboardShortcuts />
        <LocationProbe />
      </MemoryRouter>,
    )

    fireEvent.keyDown(document.body, { key: 'u' })
    expect(getByTestId('loc').textContent).toBe('/invoices')
  })

  it('returns to the parent list with u from an edit form when no field is focused (#374)', () => {
    const { getByTestId } = render(
      <MemoryRouter initialEntries={['/clients/42/edit']}>
        <KeyboardShortcuts />
        <LocationProbe />
      </MemoryRouter>,
    )

    fireEvent.keyDown(document.body, { key: 'u' })
    expect(getByTestId('loc').textContent).toBe('/clients')
  })

  it('does not fire u while a form field is focused (typing wins) (#374)', () => {
    const { getByTestId, container } = render(
      <MemoryRouter initialEntries={['/clients/42/edit']}>
        <KeyboardShortcuts />
        <LocationProbe />
        <input data-testid="field" />
      </MemoryRouter>,
    )
    const field = container.querySelector('[data-testid="field"]') as HTMLInputElement
    field.focus()

    fireEvent.keyDown(field, { key: 'u' })
    expect(getByTestId('loc').textContent).toBe('/clients/42/edit')
  })

  it('blurs the search field on Esc so j/k work again (#362)', () => {
    const { container } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input data-kbd="search" />
      </>,
    )
    const search = container.querySelector('[data-kbd="search"]') as HTMLInputElement
    search.focus()
    expect(document.activeElement).toBe(search)

    fireEvent.keyDown(search, { key: 'Escape' })
    expect(document.activeElement).not.toBe(search)
  })

  it('blurs any focused form field on Esc, not just search (#364)', () => {
    const { container } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input id="plain" />
        <textarea id="notes" />
      </>,
    )
    const plain = container.querySelector('#plain') as HTMLInputElement
    plain.focus()
    fireEvent.keyDown(plain, { key: 'Escape' })
    expect(document.activeElement).not.toBe(plain)

    const notes = container.querySelector('#notes') as HTMLTextAreaElement
    notes.focus()
    fireEvent.keyDown(notes, { key: 'Escape' })
    expect(document.activeElement).not.toBe(notes)
  })

  it('keeps focus on Esc while composing in the search field (IME cancel) (#362)', () => {
    const { container } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <input data-kbd="search" />
      </>,
    )
    const search = container.querySelector('[data-kbd="search"]') as HTMLInputElement
    search.focus()

    fireEvent.keyDown(search, { key: 'Escape', isComposing: true })
    expect(document.activeElement).toBe(search)
  })

  it('opens the command palette on Ctrl/⌘+K and navigates with j + Enter (#370)', async () => {
    const { getByRole, getByTestId, queryByRole } = renderWithProviders(
      <>
        <KeyboardShortcuts />
        <LocationProbe />
      </>,
    )
    expect(queryByRole('dialog')).not.toBeInTheDocument()

    fireEvent.keyDown(document.body, { key: 'k', ctrlKey: true })
    expect(getByRole('dialog')).toBeInTheDocument()

    // cursor starts on the first command (dashboard); j → quotes, Enter goes.
    fireEvent.keyDown(document.body, { key: 'j' })
    fireEvent.keyDown(document.body, { key: 'Enter' })
    await waitFor(() => {
      expect(getByTestId('loc')).toHaveTextContent('/quotes')
    })
    expect(queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('closes the command palette on Esc (#370)', () => {
    const { getByRole, queryByRole } = renderWithProviders(<KeyboardShortcuts />)
    fireEvent.keyDown(document.body, { key: 'k', metaKey: true })
    expect(getByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(document.body, { key: 'Escape' })
    expect(queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('groups palette commands with non-selectable headers (design 案A, #370)', () => {
    const { getByRole, getAllByRole } = renderWithProviders(<KeyboardShortcuts />)
    fireEvent.keyDown(document.body, { key: 'k', metaKey: true })

    const dialog = getByRole('dialog')
    // Group headers are presentation (not options); 10 navigable options remain.
    expect(getAllByRole('option')).toHaveLength(10)
    expect(dialog.querySelectorAll('.cmdp-grp')).toHaveLength(3)
    // Keys render as the joined .keycombo (not the 3D .kbd).
    expect(dialog.querySelector('.keycombo')).not.toBeNull()
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
