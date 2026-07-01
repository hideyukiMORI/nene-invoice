import { Fragment, useEffect, useId, useRef, useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation, type MessageKey } from '@/shared/i18n'
import { isMacPlatform } from './platform'

/** One actionable destination in the palette. `combo` is shown as a learnable hint. */
interface Command {
  id: string
  labelKey: MessageKey
  path: string
  combo: string[]
}

/** A visual group (mirrors the sidebar IA). `ids` reference COMMANDS in order. */
interface Group {
  labelKey: MessageKey
  ids: string[]
}

/** Navigation targets, in display order. Index into this list drives the cursor. */
const COMMANDS: Command[] = [
  { id: 'dashboard', labelKey: 'admin.nav.dashboard', path: '/dashboard', combo: ['g', 'd'] },
  { id: 'quotes', labelKey: 'admin.nav.quotes', path: '/quotes', combo: ['g', 'q'] },
  { id: 'invoices', labelKey: 'admin.nav.invoices', path: '/invoices', combo: ['g', 'i'] },
  {
    id: 'bank-reconciliation',
    labelKey: 'admin.nav.bankReconciliation',
    path: '/bank-reconciliation',
    combo: ['g', 'b'],
  },
  { id: 'clients', labelKey: 'admin.nav.clients', path: '/clients', combo: ['g', 'c'] },
  { id: 'items', labelKey: 'admin.nav.items', path: '/items', combo: ['g', 'm'] },
  { id: 'templates', labelKey: 'admin.nav.templates', path: '/templates', combo: ['g', 't'] },
  { id: 'users', labelKey: 'admin.nav.users', path: '/users', combo: ['g', 'u'] },
  { id: 'settings', labelKey: 'admin.nav.settings', path: '/settings', combo: ['g', 's'] },
  { id: 'audit', labelKey: 'admin.nav.auditLogs', path: '/audit-logs', combo: ['g', 'a'] },
]

/** Grouped for display (same IA as the sidebar). Headers are not selectable. */
const GROUPS: Group[] = [
  { labelKey: 'admin.nav.group.overview', ids: ['dashboard'] },
  {
    labelKey: 'admin.nav.group.transactions',
    ids: ['quotes', 'invoices', 'bank-reconciliation', 'clients', 'items', 'templates'],
  },
  { labelKey: 'admin.nav.group.admin', ids: ['users', 'settings', 'audit'] },
]

const indexOfId = (id: string): number => COMMANDS.findIndex((c) => c.id === id)

/**
 * Command palette (⌘K / Ctrl+K) — a discoverable, actionable menu for users who
 * haven't memorised the shortcuts (#370, design 案A). Pick with j/k or ↑↓ and
 * Enter/Space; each row shows its g-prefix shortcut so the keys are learned. The
 * static `?` cheat-sheet stays for the full reference.
 */
export function CommandPalette({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const titleId = useId()
  const [cursor, setCursor] = useState(0)
  const cursorRef = useRef(0)
  const launchKey = isMacPlatform() ? '⌘K' : 'Ctrl K'

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

  const renderRow = (cmd: Command): ReactNode => {
    const i = indexOfId(cmd.id)
    return (
      <li key={cmd.id}>
        <button
          type="button"
          role="option"
          aria-selected={i === cursor}
          data-cmdp-row={i}
          className={i === cursor ? 'cmdp-row hl' : 'cmdp-row'}
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
            <span className="keycombo">
              {cmd.combo.map((cap, index) => (
                <span key={`${cap}-${String(index)}`}>{cap}</span>
              ))}
            </span>
          </span>
        </button>
      </li>
    )
  }

  return (
    <div className="modal-dim cmdp-dim">
      <button
        type="button"
        aria-label={t('common.actions.close')}
        className="absolute inset-0 size-full cursor-default"
        onClick={onClose}
      />
      <div className="cmdp" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <div className="cmdp-head" id={titleId}>
          <span className="hd-mark" aria-hidden="true" />
          <b>{t('admin.commandPalette.title')}</b>
          <span className="sub">{t('admin.commandPalette.titleEn')}</span>
          <span className="esc">
            <span className="kk">{launchKey}</span>
          </span>
        </div>
        <ul className="cmdp-list" role="listbox" aria-label={t('admin.commandPalette.title')}>
          {GROUPS.map((group) => (
            <Fragment key={group.labelKey}>
              <li className="cmdp-grp" role="presentation">
                {t(group.labelKey)}
              </li>
              {group.ids.map((id) => {
                const cmd = COMMANDS[indexOfId(id)]
                return cmd === undefined ? null : renderRow(cmd)
              })}
            </Fragment>
          ))}
        </ul>
        <div className="cmdp-foot">
          <span className="hint">
            <span className="mk">↑</span>
            <span className="mk">↓</span> / <span className="mk">j</span>
            <span className="mk">k</span> {t('admin.commandPalette.select')}
          </span>
          <span className="hint">
            <span className="mk">↵</span> {t('admin.commandPalette.go')}
          </span>
          <span className="hint spacer">
            <span className="mk">esc</span> {t('common.actions.close')}
          </span>
        </div>
      </div>
    </div>
  )
}
