import { useEffect, useId, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation, type MessageKey } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'

/** One actionable destination in the palette. `combo` is shown as a learnable hint. */
interface Command {
  id: string
  labelKey: MessageKey
  path: string
  combo: string[]
}

/** Navigation targets, mirroring the dispatcher's g-prefix map (kept in sync). */
const COMMANDS: Command[] = [
  { id: 'dashboard', labelKey: 'admin.nav.dashboard', path: '/dashboard', combo: ['g', 'd'] },
  { id: 'quotes', labelKey: 'admin.nav.quotes', path: '/quotes', combo: ['g', 'q'] },
  { id: 'invoices', labelKey: 'admin.nav.invoices', path: '/invoices', combo: ['g', 'i'] },
  { id: 'clients', labelKey: 'admin.nav.clients', path: '/clients', combo: ['g', 'c'] },
  { id: 'items', labelKey: 'admin.nav.items', path: '/items', combo: ['g', 'm'] },
  { id: 'templates', labelKey: 'admin.nav.templates', path: '/templates', combo: ['g', 't'] },
  { id: 'users', labelKey: 'admin.nav.users', path: '/users', combo: ['g', 'u'] },
  { id: 'settings', labelKey: 'admin.nav.settings', path: '/settings', combo: ['g', 's'] },
  { id: 'audit', labelKey: 'admin.nav.auditLogs', path: '/audit-logs', combo: ['g', 'a'] },
]

/**
 * Command palette (⌘K / Ctrl+K) — a discoverable, actionable menu for users who
 * haven't memorised the shortcuts (#370). Pick with j/k or ↑↓ and Enter/Space;
 * each row also shows its g-prefix shortcut so the keys are learned over time.
 * The static `?` cheat-sheet stays for the full reference.
 */
export function CommandPalette({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const titleId = useId()
  const [cursor, setCursor] = useState(0)
  const cursorRef = useRef(0)

  useEffect(() => {
    cursorRef.current = cursor
  }, [cursor])

  useEffect(() => {
    const run = (index: number): void => {
      const cmd = COMMANDS[index]
      if (cmd === undefined) return
      onClose()
      void navigate(cmd.path)
    }

    const onKey = (e: KeyboardEvent): void => {
      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault()
        setCursor((c) => Math.min(c + 1, COMMANDS.length - 1))
      } else if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault()
        setCursor((c) => Math.max(c - 1, 0))
      } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault()
        run(cursorRef.current)
      } else if (e.key === 'Escape') {
        e.preventDefault()
        onClose()
      }
    }

    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
    }
  }, [navigate, onClose])

  // Keep the cursored row visible.
  useEffect(() => {
    const el = document.querySelector(`[data-cmdp-row="${String(cursor)}"]`)
    if (el instanceof HTMLElement && typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ block: 'nearest' })
    }
  }, [cursor])

  return (
    <div className="modal-dim">
      <button
        type="button"
        aria-label={t('common.actions.close')}
        className="absolute inset-0 size-full cursor-default"
        onClick={onClose}
      />
      <div className="cmdp" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <div className="cmdp-head" id={titleId}>
          <b>{t('admin.commandPalette.title')}</b>
          <span>{t('admin.commandPalette.titleEn')}</span>
        </div>
        <ul className="cmdp-list" role="listbox" aria-label={t('admin.commandPalette.title')}>
          {COMMANDS.map((cmd, i) => (
            <li key={cmd.id}>
              <button
                type="button"
                role="option"
                aria-selected={i === cursor}
                data-cmdp-row={i}
                className={cn('cmdp-row', i === cursor && 'hl')}
                onMouseEnter={() => {
                  setCursor(i)
                }}
                onClick={() => {
                  onClose()
                  void navigate(cmd.path)
                }}
              >
                <span className="cmdp-label">{t(cmd.labelKey)}</span>
                <span className="cmdp-keys">
                  {cmd.combo.map((cap, index) => (
                    <kbd key={`${cap}-${String(index)}`} className="kbd">
                      {cap}
                    </kbd>
                  ))}
                </span>
              </button>
            </li>
          ))}
        </ul>
        <div className="cmdp-foot">{t('admin.commandPalette.hint')}</div>
      </div>
    </div>
  )
}
