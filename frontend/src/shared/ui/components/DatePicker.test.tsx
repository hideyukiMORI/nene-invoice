import { fireEvent } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { DatePicker } from './DatePicker'

describe('DatePicker', () => {
  it('shows the formatted value and opens the calendar on click', () => {
    const { container } = renderWithProviders(<DatePicker value="2026-06-15" onChange={vi.fn()} />)
    expect(container.querySelector('.dp-val')?.textContent).toBe('2026 / 06 / 15')
    expect(container.querySelector('.dp.open')).toBeNull()

    fireEvent.click(container.querySelector('.dp-field') as HTMLElement)
    expect(container.querySelector('.dp.open')).not.toBeNull()
  })

  it('emits the ISO date when a day is picked', () => {
    const onChange = vi.fn()
    const { container } = renderWithProviders(<DatePicker value="2026-06-15" onChange={onChange} />)

    fireEvent.click(container.querySelector('.dp-field') as HTMLElement)
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

    fireEvent.click(container.querySelector('.dp-field') as HTMLElement)
    fireEvent.click(getByText('Clear'))

    expect(onChange).toHaveBeenCalledWith('')
  })
})
