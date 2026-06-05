import { fireEvent } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { ClientCombobox, type ClientOption } from './ClientCombobox'

const CLIENTS: ClientOption[] = [
  {
    id: 1,
    name: '株式会社サンプル',
    name_kana: 'カブシキガイシャサンプル',
    registration_number: null,
  },
  { id: 2, name: 'Acme Foods', name_kana: 'acme foods', registration_number: 'T9' },
]

describe('ClientCombobox', () => {
  it('filters by reading (name_kana) and selects on click', () => {
    const onChange = vi.fn()
    const { container, getByRole } = renderWithProviders(
      <ClientCombobox id="c" clients={CLIENTS} value={0} onChange={onChange} />,
    )
    const input = container.querySelector('#c') as HTMLInputElement

    // Type kana that only matches the first client's reading.
    fireEvent.change(input, { target: { value: 'カブシキ' } })
    expect(getByRole('option', { name: /株式会社サンプル/ })).toBeTruthy()

    fireEvent.mouseDown(getByRole('option', { name: /株式会社サンプル/ }))
    expect(onChange).toHaveBeenCalledWith(1)
  })

  it('filters by latin reading too', () => {
    const onChange = vi.fn()
    const { container, getByRole } = renderWithProviders(
      <ClientCombobox id="c" clients={CLIENTS} value={0} onChange={onChange} />,
    )
    const input = container.querySelector('#c') as HTMLInputElement

    fireEvent.change(input, { target: { value: 'acme' } })
    fireEvent.mouseDown(getByRole('option', { name: /Acme Foods/ }))
    expect(onChange).toHaveBeenCalledWith(2)
  })

  it('offers inline registration for an unknown name and selects the new id', async () => {
    const onChange = vi.fn()
    const onCreate = vi.fn(() => Promise.resolve(99))
    const { container, getByText } = renderWithProviders(
      <ClientCombobox
        id="c"
        clients={CLIENTS}
        value={0}
        onChange={onChange}
        onCreate={onCreate}
        createLabel={(name) => `「${name}」を登録`}
      />,
    )
    const input = container.querySelector('#c') as HTMLInputElement

    fireEvent.change(input, { target: { value: '新しい取引先' } })
    const createButton = getByText('「新しい取引先」を登録')
    fireEvent.mouseDown(createButton)

    expect(onCreate).toHaveBeenCalledWith('新しい取引先')
    // onCreate resolves to 99 → selection committed.
    await vi.waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(99)
    })
  })

  it('shows the selected client name when value is set', () => {
    const { container } = renderWithProviders(
      <ClientCombobox id="c" clients={CLIENTS} value={2} onChange={vi.fn()} />,
    )
    expect((container.querySelector('#c') as HTMLInputElement).value).toBe('Acme Foods')
  })
})
