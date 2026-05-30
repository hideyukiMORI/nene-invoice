import { NavLink, Outlet } from 'react-router-dom'
import { AccountMenu } from '@/features/account-menu'
import { useTranslation } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'
import { Text } from '@/shared/ui'

/** Authenticated app chrome: header + primary nav + routed content. */
export function AppShell() {
  const { t } = useTranslation()

  const navClass = ({ isActive }: { isActive: boolean }): string =>
    cn('text-body', isActive ? 'text-fg' : 'text-fg-muted')

  return (
    <div className="min-h-screen">
      <header className="flex items-center justify-between border-b border-border bg-surface-raised px-inline-lg py-stack-sm">
        <div className="flex items-center gap-inline-lg">
          <Text variant="heading-sm">{t('common.appName')}</Text>
          <nav className="flex gap-inline-md">
            <NavLink to="/invoices" className={navClass}>
              {t('admin.nav.invoices')}
            </NavLink>
            <NavLink to="/clients" className={navClass}>
              {t('admin.nav.clients')}
            </NavLink>
            <NavLink to="/settings" className={navClass}>
              {t('admin.nav.settings')}
            </NavLink>
          </nav>
        </div>
        <AccountMenu />
      </header>
      <main className="mx-auto max-w-5xl px-inline-lg py-stack-lg">
        <Outlet />
      </main>
    </div>
  )
}
