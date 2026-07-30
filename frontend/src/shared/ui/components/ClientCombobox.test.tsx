import { fireEvent, screen } from '@testing-library/react'
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

/**
 * The name field carries `role="combobox"`; the only plain `textbox` in the tree
 * is the furigana input, which exists *only* while the inline-create form is
 * open. Querying it is therefore an accessible stand-in for "the create form is
 * (not) shown".
 */
const nameField = (): HTMLElement => screen.getByRole('combobox')
const createForm = (): HTMLElement | null => screen.queryByRole('textbox')

describe('ClientCombobox', () => {
  it('filters by reading (name_kana) and selects on click', () => {
    const onChange = vi.fn()
    renderWithProviders(<ClientCombobox id="c" clients={CLIENTS} value={0} onChange={onChange} />)

    // Type kana that only matches the first client's reading.
    fireEvent.change(nameField(), { target: { value: 'カブシキ' } })
    expect(screen.getByRole('option', { name: /株式会社サンプル/ })).toBeTruthy()

    fireEvent.mouseDown(screen.getByRole('option', { name: /株式会社サンプル/ }))
    expect(onChange).toHaveBeenCalledWith(1)
  })

  it('filters by latin reading too', () => {
    const onChange = vi.fn()
    renderWithProviders(<ClientCombobox id="c" clients={CLIENTS} value={0} onChange={onChange} />)

    fireEvent.change(nameField(), { target: { value: 'acme' } })
    fireEvent.mouseDown(screen.getByRole('option', { name: /Acme Foods/ }))
    expect(onChange).toHaveBeenCalledWith(2)
  })

  it('captures a reading and inline-registers an unknown name with it', async () => {
    const onChange = vi.fn()
    const onCreate = vi.fn(() => Promise.resolve(99))
    renderWithProviders(
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

    fireEvent.change(nameField(), { target: { value: '新しい取引先' } })
    // Step 1: open the inline-create form (does not create yet).
    fireEvent.mouseDown(screen.getByRole('button', { name: '「新しい取引先」を登録' }))
    expect(onCreate).not.toHaveBeenCalled()

    // Step 2: type a reading and confirm.
    fireEvent.change(screen.getByRole('textbox'), {
      target: { value: 'アタラシイトリヒキサキ' },
    })
    fireEvent.mouseDown(screen.getByRole('button', { name: '登録' }))

    expect(onCreate).toHaveBeenCalledWith('新しい取引先', 'アタラシイトリヒキサキ')
    // onCreate resolves to 99 → selection committed.
    await vi.waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(99)
    })
  })

  it('inline-registers with a null reading when none is entered', () => {
    const onChange = vi.fn()
    const onCreate = vi.fn(() => Promise.resolve(7))
    renderWithProviders(
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

    fireEvent.change(nameField(), { target: { value: '読みなし商店' } })
    fireEvent.mouseDown(screen.getByRole('button', { name: '「読みなし商店」を登録' }))
    fireEvent.mouseDown(screen.getByRole('button', { name: '登録' }))

    expect(onCreate).toHaveBeenCalledWith('読みなし商店', null)
  })

  it('reports the query and skips local filtering in server-search mode', () => {
    const onQueryChange = vi.fn()
    renderWithProviders(
      <ClientCombobox
        id="c"
        clients={CLIENTS}
        value={0}
        onChange={vi.fn()}
        onQueryChange={onQueryChange}
      />,
    )

    // Text that matches neither client locally; the server (parent) decides.
    fireEvent.change(nameField(), { target: { value: 'zzz' } })
    expect(onQueryChange).toHaveBeenCalledWith('zzz')
    // The parent-provided list is shown as-is — no client-side narrowing.
    expect(screen.getByRole('option', { name: /株式会社サンプル/ })).toBeTruthy()
    expect(screen.getByRole('option', { name: /Acme Foods/ })).toBeTruthy()
  })

  it('ignores the IME conversion-confirm Enter, then acts on the committed Enter (#360)', () => {
    const onChange = vi.fn()
    const onCreate = vi.fn(() => Promise.resolve(99))
    renderWithProviders(
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
    fireEvent.change(nameField(), { target: { value: '新規取引先' } })

    // Enter that confirms the IME conversion (keyCode 229) must NOT act — it
    // belongs to the IME.
    fireEvent.keyDown(nameField(), { key: 'Enter', keyCode: 229 })
    expect(createForm()).toBeNull()

    // Nothing is auto-highlighted (#366): the committed Enter alone does nothing.
    fireEvent.keyDown(nameField(), { key: 'Enter' })
    expect(createForm()).toBeNull()

    // Move to the create row with ↓, then Enter opens the inline-create form.
    fireEvent.keyDown(nameField(), { key: 'ArrowDown' })
    fireEvent.keyDown(nameField(), { key: 'Enter' })
    expect(screen.getByRole('textbox')).toBeInTheDocument()
  })

  it('does not auto-highlight a suggestion; ↓ enters the list, Enter picks (#366)', () => {
    const onChange = vi.fn()
    renderWithProviders(<ClientCombobox id="c" clients={CLIENTS} value={0} onChange={onChange} />)
    fireEvent.change(nameField(), { target: { value: 'a' } }) // matches Acme Foods

    // Suggestions are shown but nothing is highlighted — the field keeps the cursor.
    expect(screen.getByRole('option', { name: /Acme Foods/ })).not.toHaveClass('hl')
    // A bare Enter does not pick anything.
    fireEvent.keyDown(nameField(), { key: 'Enter' })
    expect(onChange).not.toHaveBeenCalled()

    // ↓ highlights the first suggestion, Enter picks it.
    fireEvent.keyDown(nameField(), { key: 'ArrowDown' })
    expect(screen.getByRole('option', { name: /Acme Foods/ })).toHaveClass('hl')
    fireEvent.keyDown(nameField(), { key: 'Enter' })
    expect(onChange).toHaveBeenCalledWith(2)
  })

  it('shows the selected client name when value is set', () => {
    renderWithProviders(<ClientCombobox id="c" clients={CLIENTS} value={2} onChange={vi.fn()} />)
    expect(nameField()).toHaveValue('Acme Foods')
  })
})
