import { useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { cn } from '@/shared/lib/cn'

/** Minimal shape the combobox needs from a client (取引先). */
export interface ClientOption {
  id: number
  name: string
  name_kana: string | null
  registration_number: string | null
}

export interface ClientComboboxProps {
  id?: string
  /** Candidate clients to suggest from (already loaded by the parent). */
  clients: ClientOption[]
  /** Selected client id; `0` means none. */
  value: number
  onChange: (clientId: number) => void
  /**
   * Inline-registers the typed name (with an optional reading) as a new client
   * and resolves to its id (or `null` on failure). When omitted, no "register"
   * affordance is shown.
   */
  onCreate?: (name: string, nameKana: string | null) => Promise<number | null>
  loading?: boolean
  invalid?: boolean
  placeholder?: string
  /**
   * Server-search mode (#328): when set, the typed text is reported here
   * (debounce in the parent) and local filtering is skipped — `clients` is shown
   * as already filtered by the server. Omit for the default local-filter mode.
   */
  onQueryChange?: (query: string) => void
  /** Label for the inline-create row, e.g. `「{name}」を新規登録`. */
  createLabel?: (name: string) => string
  /** Placeholder for the inline furigana (reading) input. */
  createKanaPlaceholder?: string
  /** Label for the inline-create confirm button. */
  createConfirmLabel?: string
  'aria-describedby'?: string
}

const norm = (s: string): string => s.trim().toLowerCase()

/**
 * Typeahead client picker (#314): filter by name / reading (name_kana) /
 * registration number, pick with mouse or keyboard, and — when the typed name
 * matches nothing — register it as a new client inline (`onCreate`). The inline
 * create expands to a small form so a reading (furigana) can be captured up
 * front (#327). Controlled by `value` (a client id); presentational, so the
 * create mutation lives in the parent hook.
 */
export function ClientCombobox({
  id,
  clients,
  value,
  onChange,
  onCreate,
  loading = false,
  invalid = false,
  placeholder,
  onQueryChange,
  createLabel,
  createKanaPlaceholder,
  createConfirmLabel,
  'aria-describedby': ariaDescribedBy,
}: ClientComboboxProps) {
  const serverFiltered = onQueryChange !== undefined
  const selected = clients.find((c) => c.id === value) ?? null

  const [text, setText] = useState(selected?.name ?? '')
  // Re-sync the field when the selection changes externally — "adjust state
  // during render" so it never clobbers what the user is typing. A just-created
  // client may not be in `clients` yet (pre-refetch); keep the text in that case.
  const [lastValue, setLastValue] = useState(value)
  if (value !== lastValue) {
    setLastValue(value)
    if (selected !== null) setText(selected.name)
  }

  const [open, setOpen] = useState(false)
  const [highlight, setHighlight] = useState(0)
  const [creating, setCreating] = useState(false)
  // Inline-create sub-form (name is the typed text; capture an optional reading).
  const [createMode, setCreateMode] = useState(false)
  const [kana, setKana] = useState('')
  const wrapRef = useRef<HTMLDivElement>(null)
  const kanaRef = useRef<HTMLInputElement>(null)

  const q = norm(text)
  // In server-search mode the parent already filtered `clients`; otherwise match
  // by name / reading / registration number locally.
  const matches = (
    serverFiltered || q === ''
      ? clients
      : clients.filter(
          (c) =>
            c.name.toLowerCase().includes(q) ||
            (c.name_kana ?? '').toLowerCase().includes(q) ||
            (c.registration_number ?? '').toLowerCase().includes(q),
        )
  ).slice(0, 50)
  const exact = clients.some((c) => c.name.toLowerCase() === q)
  const showCreate = onCreate !== undefined && q !== '' && !exact
  const rowCount = matches.length + (showCreate ? 1 : 0)

  // Focus the reading input when the inline-create form opens.
  useEffect(() => {
    if (createMode) kanaRef.current?.focus()
  }, [createMode])

  const resetCreate = (): void => {
    setCreateMode(false)
    setKana('')
  }

  const close = (): void => {
    setOpen(false)
    resetCreate()
    setText(selected?.name ?? '')
  }

  const pick = (client: ClientOption): void => {
    onChange(client.id)
    setText(client.name)
    setOpen(false)
    resetCreate()
  }

  const enterCreateMode = (): void => {
    setHighlight(matches.length)
    setCreateMode(true)
  }

  const runCreate = async (): Promise<void> => {
    if (onCreate === undefined || creating) return
    const name = text.trim()
    if (name === '') return
    setCreating(true)
    const newId = await onCreate(name, kana.trim() === '' ? null : kana.trim())
    setCreating(false)
    if (newId !== null) {
      onChange(newId)
      setText(name)
      setOpen(false)
      resetCreate()
    }
  }

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>): void => {
    // Let the IME own keys while composing — the Enter that confirms a Japanese
    // conversion (and ↑↓ that move candidates) must not be hijacked into picking
    // a suggestion or opening the create form (#360). keyCode 229 is the legacy
    // IME signal some browsers emit before isComposing flips, mirroring the
    // global dispatcher's guard.
    // eslint-disable-next-line @typescript-eslint/no-deprecated
    if (e.nativeEvent.isComposing || e.keyCode === 229) return

    if (e.key === 'ArrowDown') {
      e.preventDefault()
      if (!open) {
        setOpen(true)
        setHighlight(0)
      } else {
        setHighlight((h) => Math.min(h + 1, rowCount - 1))
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setHighlight((h) => Math.max(h - 1, 0))
    } else if (e.key === 'Enter') {
      e.preventDefault() // never submit the surrounding form
      if (!open) return
      if (highlight < matches.length) {
        const m = matches[highlight]
        if (m !== undefined) pick(m)
      } else if (showCreate) {
        enterCreateMode()
      }
    } else if (e.key === 'Escape') {
      if (open) {
        e.preventDefault()
        close()
      }
    }
  }

  const onKanaKeyDown = (e: KeyboardEvent<HTMLInputElement>): void => {
    // Same IME guard as the name field: the conversion-confirming Enter on the
    // furigana input must reach the IME, not trigger the create (#360).
    // eslint-disable-next-line @typescript-eslint/no-deprecated
    if (e.nativeEvent.isComposing || e.keyCode === 229) return

    if (e.key === 'Enter') {
      e.preventDefault()
      void runCreate()
    } else if (e.key === 'Escape') {
      e.preventDefault()
      resetCreate()
    }
  }

  return (
    <div
      ref={wrapRef}
      className={cn('combo', open && 'open')}
      onBlur={(e) => {
        if (wrapRef.current !== null && !wrapRef.current.contains(e.relatedTarget)) {
          close()
        }
      }}
    >
      <input
        type="text"
        id={id}
        className="combo-input"
        role="combobox"
        aria-expanded={open}
        aria-controls={id !== undefined ? `${id}-list` : undefined}
        aria-invalid={invalid ? true : undefined}
        aria-describedby={ariaDescribedBy}
        autoComplete="off"
        disabled={loading}
        placeholder={placeholder}
        value={text}
        onChange={(e) => {
          setText(e.target.value)
          setOpen(true)
          setHighlight(0)
          resetCreate()
          onQueryChange?.(e.target.value)
        }}
        onFocus={() => {
          setOpen(true)
        }}
        onKeyDown={onKeyDown}
      />

      {open && (loading || rowCount > 0) && (
        <ul className="combo-pop" id={id !== undefined ? `${id}-list` : undefined} role="listbox">
          {matches.map((c, i) => (
            <li key={c.id}>
              <button
                type="button"
                role="option"
                aria-selected={c.id === value}
                className={cn('combo-opt', i === highlight && 'hl')}
                onMouseEnter={() => {
                  setHighlight(i)
                }}
                onMouseDown={(e) => {
                  e.preventDefault() // keep focus; avoid blur-close before click
                  pick(c)
                }}
              >
                <span className="combo-name">{c.name}</span>
                {(c.name_kana !== null || c.registration_number !== null) && (
                  <span className="combo-sub">
                    {[c.name_kana, c.registration_number].filter(Boolean).join(' · ')}
                  </span>
                )}
              </button>
            </li>
          ))}

          {showCreate && !createMode && (
            <li>
              <button
                type="button"
                className={cn('combo-create', highlight === matches.length && 'hl')}
                onMouseEnter={() => {
                  setHighlight(matches.length)
                }}
                onMouseDown={(e) => {
                  e.preventDefault()
                  enterCreateMode()
                }}
                disabled={creating}
              >
                {createLabel !== undefined ? createLabel(text.trim()) : `+ ${text.trim()}`}
              </button>
            </li>
          )}

          {showCreate && createMode && (
            <li className="combo-createform">
              <span className="combo-createform-label">
                {createLabel !== undefined ? createLabel(text.trim()) : `+ ${text.trim()}`}
              </span>
              <input
                ref={kanaRef}
                type="text"
                className="combo-input"
                autoComplete="off"
                placeholder={createKanaPlaceholder}
                value={kana}
                onChange={(e) => {
                  setKana(e.target.value)
                }}
                onKeyDown={onKanaKeyDown}
              />
              <button
                type="button"
                className="combo-createform-confirm"
                onMouseDown={(e) => {
                  e.preventDefault()
                  void runCreate()
                }}
                disabled={creating}
              >
                {createConfirmLabel ?? '+'}
              </button>
            </li>
          )}
        </ul>
      )}
    </div>
  )
}
