import { fireEvent, waitFor } from '@testing-library/react'
import { useLocation } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { KeyboardShortcuts } from './KeyboardShortcuts'

function LocationProbe() {
  return <div data-testid="loc">{useLocation().pathname}</div>
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
