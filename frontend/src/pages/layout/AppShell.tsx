import type { ReactNode } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { AccountMenu } from '@/features/account-menu'
import { useTranslation, type MessageKey } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'

/** Monogram NN mark (logo concept 03 — overlapping N's). */
function MonoMark() {
  return (
    <svg viewBox="0 0 42 42" className="size-6" aria-hidden="true">
      <text x="-2" y="31" fontFamily="sans-serif" fontWeight="800" fontSize="32" fill="currentColor" opacity="0.4">
        N
      </text>
      <text x="11" y="31" fontFamily="sans-serif" fontWeight="800" fontSize="32" fill="currentColor">
        N
      </text>
    </svg>
  )
}

const icon = (path: ReactNode): ReactNode => (
  <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.6" className="size-[18px]">
    {path}
  </svg>
)

const ICONS: Record<string, ReactNode> = {
  dashboard: icon(
    <>
      <rect x="2.7" y="2.7" width="6" height="6" rx="1" />
      <rect x="11.3" y="2.7" width="6" height="6" rx="1" />
      <rect x="2.7" y="11.3" width="6" height="6" rx="1" />
      <rect x="11.3" y="11.3" width="6" height="6" rx="1" />
    </>,
  ),
  quotes: icon(
    <>
      <path d="M5 2.5h7l3 3v12H5z" />
      <path d="M11.5 2.5v3.5H15" />
      <path d="M7.5 10h5M7.5 13h5" />
    </>,
  ),
  invoices: icon(
    <>
      <path d="M4.5 2.5h11v15l-2-1.2-2 1.2-2-1.2-1 1.2-2-1.2-2 1.2z" />
      <path d="M7.5 7h5M7.5 10.5h5" />
    </>,
  ),
  clients: icon(
    <>
      <circle cx="10" cy="7" r="3" />
      <path d="M4 17c0-3 2.7-4.8 6-4.8S16 14 16 17" />
    </>,
  ),
  users: icon(
    <>
      <circle cx="7" cy="7" r="2.6" />
      <path d="M2.5 16.5c0-2.6 2-4.2 4.5-4.2s4.5 1.6 4.5 4.2" />
      <circle cx="14.5" cy="8" r="2.2" />
      <path d="M13 12.4c2.4-.2 4.5 1.2 4.5 4.1" />
    </>,
  ),
  settings: icon(
    <>
      <circle cx="10" cy="10" r="2.5" />
      <path d="M10 1.8v2.3M10 15.9v2.3M3.2 10H1M19 10h-2.2M5.1 5.1L3.6 3.6M16.4 16.4l-1.5-1.5M14.9 5.1l1.5-1.5M3.6 16.4l1.5-1.5" />
    </>,
  ),
}

interface NavItem {
  to: string
  label: MessageKey
  iconKey: string
}
interface NavGroup {
  label: MessageKey
  items: NavItem[]
}

const NAV: NavGroup[] = [
  {
    label: 'admin.nav.group.overview',
    items: [{ to: '/dashboard', label: 'admin.nav.dashboard', iconKey: 'dashboard' }],
  },
  {
    label: 'admin.nav.group.transactions',
    items: [
      { to: '/quotes', label: 'admin.nav.quotes', iconKey: 'quotes' },
      { to: '/invoices', label: 'admin.nav.invoices', iconKey: 'invoices' },
      { to: '/clients', label: 'admin.nav.clients', iconKey: 'clients' },
    ],
  },
  {
    label: 'admin.nav.group.admin',
    items: [
      { to: '/users', label: 'admin.nav.users', iconKey: 'users' },
      { to: '/settings', label: 'admin.nav.settings', iconKey: 'settings' },
    ],
  },
]

/** Authenticated app chrome: deep-green sidebar + topbar + routed content. */
export function AppShell() {
  const { t } = useTranslation()

  const linkClass = ({ isActive }: { isActive: boolean }): string =>
    cn(
      'flex items-center gap-inline-sm rounded-md px-inline-sm py-stack-xs text-body transition-colors',
      isActive
        ? 'bg-side-active text-side-fg font-medium'
        : 'text-side-fg-muted hover:bg-side-active/60 hover:text-side-fg',
    )

  return (
    <div className="grid min-h-screen grid-cols-[232px_1fr]">
      <aside className="flex flex-col bg-side-bg text-side-fg">
        <div className="flex items-center gap-inline-sm px-inline-md pt-stack-lg pb-stack-xs font-semibold">
          <MonoMark />
          <span className="text-heading-sm">NeNe Invoice</span>
        </div>
        <div className="px-inline-md pb-stack-md text-caption text-side-fg-muted">
          {t('common.appName')}
        </div>

        <nav className="flex-1 overflow-y-auto px-inline-sm">
          {NAV.map((group) => (
            <div key={group.label} className="mb-stack-md">
              <div className="px-inline-sm pb-stack-xs text-caption font-medium uppercase tracking-wide text-side-fg-muted">
                {t(group.label)}
              </div>
              <div className="flex flex-col gap-[2px]">
                {group.items.map((item) => (
                  <NavLink key={item.to} to={item.to} className={linkClass}>
                    <span className="shrink-0">{ICONS[item.iconKey]}</span>
                    {t(item.label)}
                  </NavLink>
                ))}
              </div>
            </div>
          ))}
        </nav>

        <div className="border-t border-side-border px-inline-sm py-stack-sm text-side-fg">
          <AccountMenu />
        </div>
      </aside>

      <div className="flex min-h-screen flex-col bg-surface">
        <header className="flex items-center justify-between border-b border-border bg-surface-raised px-inline-lg py-stack-sm">
          <div className="text-body text-fg-muted">
            NeNe Invoice <span className="opacity-40">/</span>{' '}
            <span className="font-medium text-fg">{t('common.appName')}</span>
          </div>
        </header>
        <main className="mx-auto w-full max-w-5xl flex-1 px-inline-lg py-stack-lg">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
