import { useRef, useState, type KeyboardEvent } from 'react'
import { cn } from '@/shared/lib/cn'

/** Minimal shape the suggest input needs from a line-item suggestion (#315). */
export interface LineSuggestion {
  description: string
  unit_price_cents: number
  tax_rate_bps: number
  usage_count: number
}

export interface LineItemSuggestInputProps {
  id?: string
  /** The description text (this field stays free text; the parent owns it). */
  value: string
  onChange: (description: string) => void
  /** Candidate suggestions, already loaded by the parent. */
  suggestions: LineSuggestion[]
  /** Chosen a suggestion → fill description + default unit price / tax rate. */
  onPick: (suggestion: LineSuggestion) => void
  /** Optional sub-line under each option (e.g. price · rate · used N×). */
  renderMeta?: (suggestion: LineSuggestion) => string
  'aria-label'?: string
  invalid?: boolean
}

const norm = (s: string): string => s.trim().toLowerCase()

/**
 * Typeahead for a line-item description (#315, Phase 1). The field is plain free
 * text — past descriptions are offered as you type, and picking one fills the
 * row's default unit price / tax rate (editable afterwards). It lives inside the
 * line grid, whose Enter-navigation is a *native* listener on the grid
 * container; to win the race we handle keys in the capture phase and only stop
 * propagation for the keys we consume, so Enter on free text still advances the
 * grid as usual.
 */
export function LineItemSuggestInput({
  id,
  value,
  onChange,
  suggestions,
  onPick,
  renderMeta,
  'aria-label': ariaLabel,
  invalid = false,
}: LineItemSuggestInputProps) {
  const [open, setOpen] = useState(false)
  const [highlight, setHighlight] = useState(0)
  const wrapRef = useRef<HTMLDivElement>(null)

  const q = norm(value)
  const matches = (
    q === '' ? suggestions : suggestions.filter((s) => s.description.toLowerCase().includes(q))
  ).slice(0, 50)

  const canPick = open && matches.length > 0

  const pick = (suggestion: LineSuggestion): void => {
    onPick(suggestion)
    setOpen(false)
  }

  // Capture phase: runs before the grid's native bubble listener, so we can
  // claim Enter when the menu is actionable and otherwise let the grid have it.
  const onKeyDownCapture = (e: KeyboardEvent<HTMLInputElement>): void => {
    if (e.key === 'ArrowDown') {
      if (matches.length === 0) return
      e.preventDefault()
      e.stopPropagation()
      if (!open) {
        setOpen(true)
        setHighlight(0)
      } else {
        setHighlight((h) => Math.min(h + 1, matches.length - 1))
      }
    } else if (e.key === 'ArrowUp') {
      if (!open) return
      e.preventDefault()
      e.stopPropagation()
      setHighlight((h) => Math.max(h - 1, 0))
    } else if (e.key === 'Enter') {
      if (!canPick) return // free text → let the grid advance to the next cell
      e.preventDefault()
      e.stopPropagation()
      e.nativeEvent.stopImmediatePropagation() // beat the grid's native listener
      const m = matches[Math.min(highlight, matches.length - 1)]
      if (m !== undefined) pick(m)
    } else if (e.key === 'Escape') {
      if (!open) return
      e.preventDefault()
      e.stopPropagation()
      setOpen(false)
    }
  }

  return (
    <div
      ref={wrapRef}
      className={cn('combo', open && 'open')}
      onBlur={(e) => {
        if (wrapRef.current !== null && !wrapRef.current.contains(e.relatedTarget)) {
          setOpen(false)
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
        aria-label={ariaLabel}
        aria-invalid={invalid ? true : undefined}
        autoComplete="off"
        value={value}
        onChange={(e) => {
          onChange(e.target.value)
          setOpen(true)
          setHighlight(0)
        }}
        onFocus={() => {
          setOpen(true)
        }}
        onKeyDownCapture={onKeyDownCapture}
      />

      {open && matches.length > 0 && (
        <ul className="combo-pop" id={id !== undefined ? `${id}-list` : undefined} role="listbox">
          {matches.map((s, i) => (
            <li key={s.description}>
              <button
                type="button"
                role="option"
                aria-selected={i === highlight}
                className={cn('combo-opt', i === highlight && 'hl')}
                onMouseEnter={() => {
                  setHighlight(i)
                }}
                onMouseDown={(e) => {
                  e.preventDefault() // keep focus; avoid blur-close before click
                  pick(s)
                }}
              >
                <span className="combo-name">{s.description}</span>
                {renderMeta !== undefined && <span className="combo-sub">{renderMeta(s)}</span>}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
