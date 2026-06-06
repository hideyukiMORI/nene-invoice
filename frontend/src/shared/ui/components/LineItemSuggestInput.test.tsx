import { fireEvent } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { LineItemSuggestInput, type LineSuggestion } from './LineItemSuggestInput'

const SUGGESTIONS: LineSuggestion[] = [
  {
    description: 'Web制作（基本プラン）',
    unit_price_cents: 300000,
    tax_rate_bps: 1000,
    usage_count: 5,
  },
  { description: 'コンサルティング', unit_price_cents: 30000, tax_rate_bps: 1000, usage_count: 2 },
]

describe('LineItemSuggestInput', () => {
  it('suggests by description substring and fills the row on pick', () => {
    const onChange = vi.fn()
    const onPick = vi.fn()
    const { container, getByRole } = renderWithProviders(
      <LineItemSuggestInput
        id="d"
        value=""
        onChange={onChange}
        suggestions={SUGGESTIONS}
        onPick={onPick}
      />,
    )
    const input = container.querySelector('#d') as HTMLInputElement

    fireEvent.change(input, { target: { value: 'Web' } })
    expect(onChange).toHaveBeenCalledWith('Web')

    fireEvent.mouseDown(getByRole('option', { name: /Web制作/ }))
    expect(onPick).toHaveBeenCalledWith(SUGGESTIONS[0])
  })

  it('renders the meta sub-line when provided', () => {
    const { container, getByText } = renderWithProviders(
      <LineItemSuggestInput
        id="d"
        value=""
        onChange={vi.fn()}
        suggestions={SUGGESTIONS}
        onPick={vi.fn()}
        renderMeta={(s) => `¥${String(s.unit_price_cents)} · ${String(s.usage_count)}×`}
      />,
    )
    fireEvent.focus(container.querySelector('#d') as HTMLInputElement)
    expect(getByText('¥300000 · 5×')).toBeTruthy()
  })

  it('picks the highlighted suggestion on Enter and stops the event', () => {
    const onPick = vi.fn()
    // value is the filter text (the field is free text the parent controls).
    const { container, getByRole } = renderWithProviders(
      <LineItemSuggestInput
        id="d"
        value="コンサル"
        onChange={vi.fn()}
        suggestions={SUGGESTIONS}
        onPick={onPick}
      />,
    )
    const input = container.querySelector('#d') as HTMLInputElement

    fireEvent.focus(input) // open the menu
    expect(getByRole('option', { name: /コンサルティング/ })).toBeTruthy()
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onPick).toHaveBeenCalledWith(SUGGESTIONS[1])
  })

  it('does not pick on Enter when there are no matches (free text passes through)', () => {
    const onPick = vi.fn()
    const { container } = renderWithProviders(
      <LineItemSuggestInput
        id="d"
        value="完全に新しい品目"
        onChange={vi.fn()}
        suggestions={SUGGESTIONS}
        onPick={onPick}
      />,
    )
    const input = container.querySelector('#d') as HTMLInputElement

    fireEvent.focus(input)
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onPick).not.toHaveBeenCalled()
  })
})
