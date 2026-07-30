import { fireEvent, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { DatePicker } from './DatePicker'

/** The icon button that toggles the calendar popover (`common.datePicker.open`). */
const openButton = (): HTMLElement => screen.getByRole('button', { name: 'Open calendar' })
/** The editable date field (`.dp-input`) — the only textbox rendered. */
const dateInput = (): HTMLElement => screen.getByRole('textbox')

describe('DatePicker', () => {
  it('shows the value as editable text and opens the calendar via the icon', () => {
    renderWithProviders(<DatePicker value="2026-06-15" onChange={vi.fn()} />)
    expect(dateInput()).toHaveValue('2026/06/15')
    expect(openButton()).toHaveAttribute('aria-expanded', 'false')

    fireEvent.click(openButton())
    expect(openButton()).toHaveAttribute('aria-expanded', 'true')
  })

  it('emits the ISO date when a day is picked', () => {
    const onChange = vi.fn()
    renderWithProviders(<DatePicker value="2026-06-15" onChange={onChange} />)

    fireEvent.click(openButton())
    fireEvent.click(screen.getByRole('button', { name: '20' }))

    expect(onChange).toHaveBeenCalledWith('2026-06-20')
  })

  it('clears the value via the clear button', () => {
    const onChange = vi.fn()
    renderWithProviders(<DatePicker value="2026-06-15" onChange={onChange} />)

    fireEvent.click(openButton())
    fireEvent.click(screen.getByRole('button', { name: 'Clear' }))

    expect(onChange).toHaveBeenCalledWith('')
  })

  it('commits a typed date on blur (accepts - or / separators, 1–2 digits)', () => {
    const onChange = vi.fn()
    renderWithProviders(<DatePicker value="" onChange={onChange} />)
    const input = dateInput()

    fireEvent.change(input, { target: { value: '2026/7/3' } })
    fireEvent.blur(input)

    expect(onChange).toHaveBeenCalledWith('2026-07-03')
  })

  it('commits a typed date on Enter without submitting the form', () => {
    const onChange = vi.fn()
    renderWithProviders(<DatePicker value="" onChange={onChange} />)
    const input = dateInput()

    fireEvent.change(input, { target: { value: '2026-12-31' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onChange).toHaveBeenCalledWith('2026-12-31')
  })

  it('reverts an invalid typed date on blur without emitting', () => {
    const onChange = vi.fn()
    renderWithProviders(<DatePicker value="2026-06-15" onChange={onChange} />)
    const input = dateInput()

    fireEvent.change(input, { target: { value: '2026/02/30' } }) // not a real date
    fireEvent.blur(input)

    expect(onChange).not.toHaveBeenCalled()
    expect(input).toHaveValue('2026/06/15')
  })

  it('keeps the closed calendar out of the tab order via inert (#358)', () => {
    renderWithProviders(<DatePicker value="2026-06-15" onChange={vi.fn()} />)

    // Closed: inert so its ~45 buttons cannot trap Tab focus.
    expect(screen.getByRole('dialog', { hidden: true })).toHaveAttribute('inert')

    fireEvent.click(openButton())
    // Open: interactive again.
    expect(screen.getByRole('dialog', { hidden: true })).not.toHaveAttribute('inert')
  })
})
