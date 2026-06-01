import { useTranslation } from '@/shared/i18n'
import { useAccountMenu } from '../hooks/use-account-menu'

/** Sidebar account summary + sign-out, themed for the deep-green chrome. */
export function AccountMenu() {
  const { t } = useTranslation()
  const { email, onSignOut } = useAccountMenu()

  return (
    <div className="flex flex-col gap-stack-xs">
      {email !== null && (
        <span className="truncate text-caption text-side-fg-muted" title={email}>
          {t('admin.account.signedInAs', { email })}
        </span>
      )}
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
