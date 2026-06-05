import { fireEvent } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { DatePicker } from './DatePicker'

describe('DatePicker', () => {
  it('shows the value as editable text and opens the calendar via the icon', () => {
    const { container } = renderWithProviders(<DatePicker value="2026-06-15" onChange={vi.fn()} />)
    expect((container.querySelector('.dp-input') as HTMLInputElement).value).toBe('2026/06/15')
    expect(container.querySelector('.dp.open')).toBeNull()

    fireEvent.click(container.querySelector('.dp-ico-btn') as HTMLElement)
    expect(container.querySelector('.dp.open')).not.toBeNull()
  })

  it('emits the ISO date when a day is picked', () => {
    const onChange = vi.fn()
    const { container } = renderWithProviders(<DatePicker value="2026-06-15" onChange={onChange} />)

    fireEvent.click(container.querySelector('.dp-ico-btn') as HTMLElement)
    const day20 = [...container.querySelectorAll('.dp-day:not(.out)')].find(
      (d) => d.textContent === '20',
    )
    fireEvent.click(day20 as HTMLElement)

    expect(onChange).toHaveBeenCalledWith('2026-06-20')
  })

  it('clears the value via the clear button', () => {
    const onChange = vi.fn()
    const { container, getByText } = renderWithProviders(
      <DatePicker value="2026-06-15" onChange={onChange} />,
    )

    fireEvent.click(container.querySelector('.dp-ico-btn') as HTMLElement)
    fireEvent.click(getByText('Clear'))

    expect(onChange).toHaveBeenCalledWith('')
  })

  it('commits a typed date on blur (accepts - or / separators, 1–2 digits)', () => {
    const onChange = vi.fn()
    const { container } = renderWithProviders(<DatePicker value="" onChange={onChange} />)
    const input = container.querySelector('.dp-input') as HTMLInputElement

    fireEvent.change(input, { target: { value: '2026/7/3' } })
    fireEvent.blur(input)

    expect(onChange).toHaveBeenCalledWith('2026-07-03')
  })

  it('commits a typed date on Enter without submitting the form', () => {
    const onChange = vi.fn()
    const { container } = renderWithProviders(<DatePicker value="" onChange={onChange} />)
    const input = container.querySelector('.dp-input') as HTMLInputElement

    fireEvent.change(input, { target: { value: '2026-12-31' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onChange).toHaveBeenCalledWith('2026-12-31')
  })

  it('reverts an invalid typed date on blur without emitting', () => {
    const onChange = vi.fn()
    const { container } = renderWithProviders(<DatePicker value="2026-06-15" onChange={onChange} />)
    const input = container.querySelector('.dp-input') as HTMLInputElement

    fireEvent.change(input, { target: { value: '2026/02/30' } }) // not a real date
    fireEvent.blur(input)

    expect(onChange).not.toHaveBeenCalled()
    expect(input.value).toBe('2026/06/15')
  })
})
