import { Outlet } from 'react-router-dom'
import { AccountMenu } from '@/features/account-menu'
import { useTranslation } from '@/shared/i18n'
import { Text } from '@/shared/ui'

/** Authenticated app chrome: header + routed content. */
export function AppShell() {
  const { t } = useTranslation()

  return (
    <div className="min-h-screen">
      <header className="flex items-center justify-between border-b border-border bg-surface-raised px-inline-lg py-stack-sm">
        <Text variant="heading-sm">{t('common.appName')}</Text>
        <AccountMenu />
      </header>
      <main className="mx-auto max-w-5xl px-inline-lg py-stack-lg">
        <Outlet />
      </main>
    </div>
  )
}
