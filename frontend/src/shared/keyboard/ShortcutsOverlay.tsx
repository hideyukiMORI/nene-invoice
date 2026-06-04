import { useEffect, useId } from 'react'
import { useTranslation } from '@/shared/i18n'
import { isMacPlatform } from './platform'
import { MOD, SHORTCUT_GROUPS, type ShortcutCombo, type ShortcutGroup } from './shortcuts-data'

/**
 * `?` shortcut cheat-sheet (Issue #257). Same tone as `.modal` (square, pop
 * shadow) but widened to a two-column cheat-sheet with Roboto Mono keycaps.
 * Esc and backdrop close it. Labels are ja主 / en副 per the spec.
 */
export interface ShortcutsOverlayProps {
  onClose: () => void
}

function capLabel(cap: string, mac: boolean): string {
  if (cap === MOD) return mac ? '⌘' : 'Ctrl'
  return cap
}

function isWide(label: string): boolean {
  return label.length > 1
}

function Combo({ combo, mac }: { combo: ShortcutCombo; mac: boolean }) {
  return (
    <span className="sc-keys">
      {combo.caps.map((cap, index) => {
        const label = capLabel(cap, mac)
        return (
          <span key={`${cap}-${String(index)}`} className="sc-keycap-wrap">
            {index > 0 && combo.join === 'then' && <span className="sc-join">→</span>}
            {index > 0 && combo.join === 'plus' && <span className="sc-join">+</span>}
            <kbd className={isWide(label) ? 'kbd wide' : 'kbd'}>{label}</kbd>
          </span>
        )
      })}
    </span>
  )
}

function Group({ group, mac }: { group: ShortcutGroup; mac: boolean }) {
  return (
    <>
      <div className="sc-grp">
        {group.ja}
        <span className="sc-grp-en"> · {group.en}</span>
      </div>
      {group.rows.map((row) => (
        <div key={row.en} className="sc-row">
          <span className="lbl">
            {row.ja}
            <small>{row.en}</small>
          </span>
          <span className="sc-keys-row">
            {row.combos.map((combo, index) => (
              <Combo key={index} combo={combo} mac={mac} />
            ))}
          </span>
        </div>
      ))}
    </>
  )
}

export function ShortcutsOverlay({ onClose }: ShortcutsOverlayProps) {
  const { t } = useTranslation()
  const titleId = useId()
  const mac = isMacPlatform()

  // Split groups across the two cheat-sheet columns.
  const mid = Math.ceil(SHORTCUT_GROUPS.length / 2)
  const columns = [SHORTCUT_GROUPS.slice(0, mid), SHORTCUT_GROUPS.slice(mid)]

  useEffect(() => {
    const onKey = (e: KeyboardEvent): void => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
    }
  }, [onClose])

  return (
    <div className="modal-dim">
      <button
        type="button"
        aria-label={t('common.actions.close')}
        className="absolute inset-0 size-full cursor-default"
        onClick={onClose}
      />
      <div className="sc-modal" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <div className="sc-head">
          <span className="sc-title" id={titleId}>
            <b>{t('admin.shortcuts.title')}</b>
            <span>{t('admin.shortcuts.titleEn')}</span>
          </span>
          <button type="button" className="sc-x" onClick={onClose}>
            <kbd className="kbd wide">Esc</kbd> {t('common.actions.close')}
          </button>
        </div>
        <div className="sc-body">
          {columns.map((groups, index) => (
            <div key={index} className="sc-col">
              {groups.map((group) => (
                <Group key={group.en} group={group} mac={mac} />
              ))}
            </div>
          ))}
        </div>
        <div className="sc-foot">
          <span>{t('admin.shortcuts.footHint')}</span>
          <span>{mac ? t('admin.shortcuts.footModMac') : t('admin.shortcuts.footModOther')}</span>
        </div>
      </div>
    </div>
  )
}
