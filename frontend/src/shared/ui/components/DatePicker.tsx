import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import { useTranslation } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'

/**
 * Custom date picker (design 04) — replaces the native OS date control with a
 * themed calendar popover. Controlled: `value` is a `YYYY-MM-DD` string (or '');
 * `onChange` emits the same. Closes on outside click / Esc; flips up/right near
 * the viewport edge.
 */
export interface DatePickerProps {
  id?: string
  value: string
  onChange: (value: string) => void
  placeholder?: string
  'aria-describedby'?: string
}

const pad = (n: number): string => (n < 10 ? `0${String(n)}` : String(n))
const isoOf = (d: Date): string =>
  `${String(d.getFullYear())}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
const fmt = (d: Date): string =>
  `${String(d.getFullYear())} / ${pad(d.getMonth() + 1)} / ${pad(d.getDate())}`
const parseIso = (s: string): Date | null => {
  const p = s.split('-')
  if (p.length !== 3) return null
  const d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]))
  return Number.isNaN(d.getTime()) ? null : d
}
const midnight = (d: Date): number => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()

export function DatePicker({
  id,
  value,
  onChange,
  placeholder,
  'aria-describedby': ariaDescribedBy,
}: DatePickerProps) {
  const { t, locale } = useTranslation()
  const selected = parseIso(value)

  const [open, setOpen] = useState(false)
  const [view, setView] = useState(() => selected ?? new Date())
  const [flip, setFlip] = useState({ up: false, right: false })
  const wrapRef = useRef<HTMLDivElement>(null)
  const popRef = useRef<HTMLDivElement>(null)

  const dow =
    locale === 'ja'
      ? ['日', '月', '火', '水', '木', '金', '土']
      : ['S', 'M', 'T', 'W', 'T', 'F', 'S']

  useEffect(() => {
    if (!open) return
    const onDocClick = (e: MouseEvent): void => {
      if (wrapRef.current !== null && !wrapRef.current.contains(e.target as Node)) setOpen(false)
    }
    const onKey = (e: KeyboardEvent): void => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onDocClick)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onDocClick)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  useLayoutEffect(() => {
    if (!open || popRef.current === null) return
    const r = popRef.current.getBoundingClientRect()
    setFlip({ up: r.bottom > window.innerHeight - 8, right: r.right > window.innerWidth - 8 })
  }, [open])

  const openPicker = (): void => {
    setView(selected ?? new Date())
    setFlip({ up: false, right: false })
    setOpen(true)
  }

  const commit = (next: string): void => {
    onChange(next)
    setOpen(false)
  }

  const y = view.getFullYear()
  const m = view.getMonth()
  const startDow = new Date(y, m, 1).getDay()
  const gridStart = new Date(y, m, 1 - startDow)
  const today = midnight(new Date())
  const selDay = selected !== null ? midnight(selected) : null

  const cells = Array.from({ length: 42 }, (_, i) => {
    const dt = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i)
    const tMid = midnight(dt)
    return {
      iso: isoOf(dt),
      day: dt.getDate(),
      out: dt.getMonth() !== m,
      today: tMid === today,
      sel: selDay !== null && tMid === selDay,
      dow: dt.getDay(),
    }
  })

  return (
    <div ref={wrapRef} className={cn('dp', open && 'open')}>
      <button
        type="button"
        id={id}
        className="dp-field input"
        aria-describedby={ariaDescribedBy}
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() => {
          if (open) setOpen(false)
          else openPicker()
        }}
      >
        <span className={cn('dp-val', selected === null && 'is-ph')}>
          {selected !== null ? fmt(selected) : (placeholder ?? t('common.datePicker.placeholder'))}
        </span>
        <svg
          className="dp-ico"
          viewBox="0 0 18 18"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
        >
          <rect x="2.5" y="3.8" width="13" height="11.7" rx="1.6" />
          <path d="M2.5 7.2h13M6 2.2v3M12 2.2v3" />
        </svg>
      </button>

      <div
        ref={popRef}
        className={cn('dp-pop', flip.up && 'up', flip.right && 'right')}
        role="dialog"
      >
        <div className="dp-head">
          <button
            type="button"
            className="dp-nav"
            aria-label={t('common.datePicker.prevMonth')}
            onClick={() => {
              setView(new Date(y, m - 1, 1))
            }}
          >
            <svg
              viewBox="0 0 16 16"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.7"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M10 3.5L5.5 8 10 12.5" />
            </svg>
          </button>
          <div className="dp-title">{t('common.datePicker.title', { year: y, month: m + 1 })}</div>
          <button
            type="button"
            className="dp-nav"
            aria-label={t('common.datePicker.nextMonth')}
            onClick={() => {
              setView(new Date(y, m + 1, 1))
            }}
          >
            <svg
              viewBox="0 0 16 16"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.7"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M6 3.5L10.5 8 6 12.5" />
            </svg>
          </button>
        </div>

        <div className="dp-week">
          {dow.map((w, i) => (
            <span key={i} className={cn('dp-w', i === 0 && 'sun', i === 6 && 'sat')}>
              {w}
            </span>
          ))}
        </div>

        <div className="dp-grid">
          {cells.map((c) => (
            <button
              key={c.iso}
              type="button"
              className={cn(
                'dp-day',
                c.out && 'out',
                c.today && 'today',
                c.sel && 'sel',
                c.dow === 0 && 'sun',
                c.dow === 6 && 'sat',
              )}
              onClick={() => {
                commit(c.iso)
              }}
            >
              {c.day}
            </button>
          ))}
        </div>

        <div className="dp-foot">
          <button
            type="button"
            className="dp-today-btn"
            onClick={() => {
              commit(isoOf(new Date()))
            }}
          >
            {t('common.datePicker.today')}
          </button>
          <button
            type="button"
            onClick={() => {
              commit('')
            }}
          >
            {t('common.datePicker.clear')}
          </button>
        </div>
      </div>
    </div>
  )
}
