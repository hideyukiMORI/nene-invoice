import { Link } from 'react-router-dom'
import { LOCALES, useTranslation } from '@/shared/i18n'
import { openShortcutsOverlay } from '@/shared/keyboard'
import { cn } from '@/shared/lib/cn'
import { useAccountMenu } from '../hooks/use-account-menu'

/** Sidebar footer (design 04 `.side-foot`): user identity + keyboard-shortcut
 *  launcher + language segment + sign-out, themed for the deep-green chrome. */
export function AccountMenu() {
  const { t, locale, setLocale } = useTranslation()
  const { email, role, onSignOut } = useAccountMenu()

  // charAt (rather than indexed access) sidesteps noUncheckedIndexedAccess:
  // the length guard already ensures a char is present.
  const initial = email !== null && email.length > 0 ? email.charAt(0).toUpperCase() : '—'

  return (
    <div className="side-foot">
      <div className="sf-user">
        <span className="side-avatar" aria-hidden="true">
          {initial}
        </span>
        <div className="min-w-0">
          {email !== null && (
            <div className="sf-mail truncate" title={email}>
              {email}
            </div>
          )}
          {role !== null && <div className="sf-role">{t(`admin.users.role.${role}`)}</div>}
        </div>
      </div>

      <button
        type="button"
        className="sf-help"
        onClick={openShortcutsOverlay}
        aria-keyshortcuts="?"
      >
        <span>{t('admin.nav.shortcuts')}</span>
        <span className="sf-help-k">?</span>
      </button>

      <div className="sf-lang" role="group" aria-label={t('common.locale.label')}>
        {LOCALES.map((loc) => {
          const active = locale === loc.id
          return (
            <button
              key={loc.id}
              type="button"
              aria-pressed={active}
              onClick={() => {
                setLocale(loc.id)
              }}
              className={cn('sf-lang-btn', active && 'is-on')}
            >
              {t(loc.labelKey)}
            </button>
          )
        })}
      </div>

      <button type="button" className="sf-logout" onClick={onSignOut}>
        {t('common.actions.signOut')}
      </button>

      <Link to="/help#disclaimer" className="sf-legal">
        {t('admin.nav.disclaimer')}
      </Link>
    </div>
  )
}
