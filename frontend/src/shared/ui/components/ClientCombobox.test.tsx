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

  it('captures a reading and inline-registers an unknown name with it', async () => {
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
        createConfirmLabel="登録"
      />,
    )
    const input = container.querySelector('#c') as HTMLInputElement

    fireEvent.change(input, { target: { value: '新しい取引先' } })
    // Step 1: open the inline-create form (does not create yet).
    fireEvent.mouseDown(getByText('「新しい取引先」を登録'))
    expect(onCreate).not.toHaveBeenCalled()

    // Step 2: type a reading and confirm.
    const kana = container.querySelector('.combo-createform .combo-input') as HTMLInputElement
    fireEvent.change(kana, { target: { value: 'アタラシイトリヒキサキ' } })
    fireEvent.mouseDown(getByText('登録'))

    expect(onCreate).toHaveBeenCalledWith('新しい取引先', 'アタラシイトリヒキサキ')
    // onCreate resolves to 99 → selection committed.
    await vi.waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(99)
    })
  })

  it('inline-registers with a null reading when none is entered', () => {
    const onChange = vi.fn()
    const onCreate = vi.fn(() => Promise.resolve(7))
    const { container, getByText } = renderWithProviders(
      <ClientCombobox
        id="c"
        clients={CLIENTS}
        value={0}
        onChange={onChange}
        onCreate={onCreate}
        createLabel={(name) => `「${name}」を登録`}
        createConfirmLabel="登録"
      />,
    )
    const input = container.querySelector('#c') as HTMLInputElement

    fireEvent.change(input, { target: { value: '読みなし商店' } })
    fireEvent.mouseDown(getByText('「読みなし商店」を登録'))
    fireEvent.mouseDown(getByText('登録'))

    expect(onCreate).toHaveBeenCalledWith('読みなし商店', null)
  })

  it('reports the query and skips local filtering in server-search mode', () => {
    const onQueryChange = vi.fn()
    const { container, getByRole } = renderWithProviders(
      <ClientCombobox
        id="c"
        clients={CLIENTS}
        value={0}
        onChange={vi.fn()}
        onQueryChange={onQueryChange}
      />,
    )
    const input = container.querySelector('#c') as HTMLInputElement

    // Text that matches neither client locally; the server (parent) decides.
    fireEvent.change(input, { target: { value: 'zzz' } })
    expect(onQueryChange).toHaveBeenCalledWith('zzz')
    // The parent-provided list is shown as-is — no client-side narrowing.
    expect(getByRole('option', { name: /株式会社サンプル/ })).toBeTruthy()
    expect(getByRole('option', { name: /Acme Foods/ })).toBeTruthy()
  })

  it('ignores the IME conversion-confirm Enter, then acts on the committed Enter (#360)', () => {
    const onChange = vi.fn()
    const onCreate = vi.fn(() => Promise.resolve(99))
    const { container } = renderWithProviders(
      <ClientCombobox
        id="c"
        clients={CLIENTS}
        value={0}
        onChange={onChange}
        onCreate={onCreate}
        createLabel={(name) => `「${name}」を登録`}
        createConfirmLabel="登録"
      />,
    )
    const input = container.querySelector('#c') as HTMLInputElement
    fireEvent.change(input, { target: { value: '新規取引先' } })

    // Enter that confirms the IME conversion (keyCode 229) must NOT act — it
    // belongs to the IME.
    fireEvent.keyDown(input, { key: 'Enter', keyCode: 229 })
    expect(container.querySelector('.combo-createform')).toBeNull()

    // Nothing is auto-highlighted (#366): the committed Enter alone does nothing.
    fireEvent.keyDown(input, { key: 'Enter' })
    expect(container.querySelector('.combo-createform')).toBeNull()

    // Move to the create row with ↓, then Enter opens the inline-create form.
    fireEvent.keyDown(input, { key: 'ArrowDown' })
    fireEvent.keyDown(input, { key: 'Enter' })
    expect(container.querySelector('.combo-createform')).not.toBeNull()
  })

  it('does not auto-highlight a suggestion; ↓ enters the list, Enter picks (#366)', () => {
    const onChange = vi.fn()
    const { container } = renderWithProviders(
      <ClientCombobox id="c" clients={CLIENTS} value={0} onChange={onChange} />,
    )
    const input = container.querySelector('#c') as HTMLInputElement
    fireEvent.change(input, { target: { value: 'a' } }) // matches Acme Foods

    // Suggestions are shown but nothing is highlighted — the field keeps the cursor.
    expect(container.querySelector('.combo-opt.hl')).toBeNull()
    // A bare Enter does not pick anything.
    fireEvent.keyDown(input, { key: 'Enter' })
    expect(onChange).not.toHaveBeenCalled()

    // ↓ highlights the first suggestion, Enter picks it.
    fireEvent.keyDown(input, { key: 'ArrowDown' })
    expect(container.querySelector('.combo-opt.hl')).not.toBeNull()
    fireEvent.keyDown(input, { key: 'Enter' })
    expect(onChange).toHaveBeenCalledWith(2)
  })

  it('shows the selected client name when value is set', () => {
    const { container } = renderWithProviders(
      <ClientCombobox id="c" clients={CLIENTS} value={2} onChange={vi.fn()} />,
    )
    expect((container.querySelector('#c') as HTMLInputElement).value).toBe('Acme Foods')
  })
})
