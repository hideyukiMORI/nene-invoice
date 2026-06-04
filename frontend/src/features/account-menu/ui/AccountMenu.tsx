import { LOCALES, useTranslation } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'
import { useAccountMenu } from '../hooks/use-account-menu'

/** Sidebar account summary + language switch + sign-out, themed for the chrome. */
export function AccountMenu() {
  const { t, locale, setLocale } = useTranslation()
  const { email, onSignOut } = useAccountMenu()

  return (
    <div className="flex flex-col gap-stack-sm">
      {email !== null && (
        <span className="truncate text-caption text-side-fg-muted" title={email}>
          {t('admin.account.signedInAs', { email })}
        </span>
      )}

      <div
        className="flex border border-side-border"
        role="group"
        aria-label={t('common.locale.label')}
      >
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
              className={cn(
                'flex-1 px-inline-sm py-stack-xs text-caption transition-colors',
                'focus-visible:outline-2 focus-visible:outline-focus-ring',
                active
                  ? 'bg-side-active font-medium text-side-fg'
                  : 'text-side-fg-muted hover:bg-side-active/60 hover:text-side-fg',
              )}
            >
              {t(loc.labelKey)}
            </button>
          )
        })}
      </div>

      <button
        type="button"
        onClick={onSignOut}
        className="w-full border border-side-border px-inline-sm py-stack-xs text-body text-side-fg transition-colors hover:bg-side-active focus-visible:outline-2 focus-visible:outline-focus-ring"
      >
        {t('common.actions.signOut')}
      </button>
    </div>
  )
}
