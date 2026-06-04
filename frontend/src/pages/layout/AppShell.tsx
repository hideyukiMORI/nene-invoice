import { useState, type ReactNode } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { AccountMenu } from '@/features/account-menu'
import { useTranslation, type MessageKey } from '@/shared/i18n'
import { KeyboardShortcuts, openShortcutsOverlay } from '@/shared/keyboard'
import { cn } from '@/shared/lib/cn'

/** Monogram NN mark (logo concept 03 — overlapping N's). */
function MonoMark() {
  return (
    <svg viewBox="0 0 42 42" className="size-6" aria-hidden="true">
      <text
        x="-2"
        y="31"
        fontFamily="sans-serif"
        fontWeight="800"
        fontSize="32"
        fill="currentColor"
        opacity="0.4"
      >
        N
      </text>
      <text
        x="11"
        y="31"
        fontFamily="sans-serif"
        fontWeight="800"
        fontSize="32"
        fill="currentColor"
      >
        N
      </text>
    </svg>
  )
}

const icon = (path: ReactNode): ReactNode => (
  <svg
    viewBox="0 0 20 20"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.6"
    strokeLinecap="round"
    strokeLinejoin="round"
    className="size-4.5"
  >
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
  audit: icon(
    <>
      <path d="M5 2.5h7l3 3v12H5z" />
      <path d="M11.5 2.5v3.5H15" />
      <path d="M7.5 9.5l1.5 1.5 3-3.5" />
      <path d="M7.5 13.5h5" />
    </>,
  ),
  help: icon(
    <>
      <circle cx="10" cy="10" r="7.5" />
      <path d="M7.8 7.8a2.2 2.2 0 1 1 3 2.05c-.7.3-1 .7-1 1.45v.4" />
      <path d="M9.8 14.2v.1" />
    </>,
  ),
  more: icon(
    <>
      <circle cx="4" cy="10" r="1.4" />
      <circle cx="10" cy="10" r="1.4" />
      <circle cx="16" cy="10" r="1.4" />
    </>,
  ),
}

/** Hamburger (mobile drawer toggle). */
const burgerIcon = (
  <svg
    viewBox="0 0 20 20"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.7"
    strokeLinecap="round"
    className="size-4.5"
  >
    <path d="M3 5.5h14M3 10h14M3 14.5h14" />
  </svg>
)

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
      { to: '/audit-logs', label: 'admin.nav.auditLogs', iconKey: 'audit' },
      { to: '/help', label: 'admin.nav.help', iconKey: 'help' },
      { to: '/settings', label: 'admin.nav.settings', iconKey: 'settings' },
    ],
  },
]

/** Primary tabs for the mobile bottom bar (thumb-reachable). The remaining nav
 *  items (users / settings / audit) live behind the 「メニュー」 tab, which opens
 *  the sidebar drawer. */
interface BottomTab {
  to: string
  label: MessageKey
  iconKey: string
}
const BOTTOM_TABS: BottomTab[] = [
  { to: '/dashboard', label: 'admin.bottomNav.dashboard', iconKey: 'dashboard' },
  { to: '/quotes', label: 'admin.bottomNav.quotes', iconKey: 'quotes' },
  { to: '/invoices', label: 'admin.bottomNav.invoices', iconKey: 'invoices' },
  { to: '/clients', label: 'admin.bottomNav.clients', iconKey: 'clients' },
]
/** Routes that live behind the 「メニュー」 tab (not direct bottom tabs). */
const DRAWER_ROUTES = ['/users', '/audit-logs', '/help', '/settings']

interface BottomNavProps {
  pathname: string
  menuActive: boolean
  onMenu: () => void
}

function BottomNav({ pathname, menuActive, onMenu }: BottomNavProps) {
  const { t } = useTranslation()
  return (
    <nav className="bottom-nav" aria-label={t('admin.nav.primary')}>
      {BOTTOM_TABS.map((tab) => {
        const active = pathname === tab.to || pathname.startsWith(`${tab.to}/`)
        return (
          <NavLink key={tab.to} to={tab.to} className={cn('bn-item', active && 'active')}>
            <span className="bn-ico">{ICONS[tab.iconKey]}</span>
            {t(tab.label)}
          </NavLink>
        )
      })}
      <button
        type="button"
        className={cn('bn-item', menuActive && 'active')}
        aria-label={t('admin.bottomNav.menu')}
        onClick={onMenu}
      >
        <span className="bn-ico">{ICONS.more}</span>
        {t('admin.bottomNav.menu')}
      </button>
    </nav>
  )
}

/** Authenticated app chrome: deep-green sidebar (off-canvas drawer on mobile)
 *  + topbar + routed content. Responsive styling lives in the theme layer. */
export function AppShell() {
  const { t } = useTranslation()
  const { pathname } = useLocation()
  const [navOpen, setNavOpen] = useState(false)
  const closeNav = (): void => {
    setNavOpen(false)
  }

  // Breadcrumb tail = the nav item whose route prefixes the current path.
  const activeItem = NAV.flatMap((g) => g.items).find(
    (item) => pathname === item.to || pathname.startsWith(`${item.to}/`),
  )

  const linkClass = ({ isActive }: { isActive: boolean }): string =>
    cn(
      'flex items-center gap-inline-sm px-inline-sm py-stack-xs text-body transition-colors',
      isActive
        ? 'bg-side-active text-side-fg font-medium'
        : 'text-side-fg-muted hover:bg-side-active/60 hover:text-side-fg',
    )

  return (
    <div className={cn('app', navOpen && 'nav-open')}>
      <KeyboardShortcuts />
      <aside className="side flex flex-col bg-side-bg text-side-fg">
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
              <div className="flex flex-col gap-0.5">
                {group.items.map((item) => (
                  <NavLink key={item.to} to={item.to} className={linkClass} onClick={closeNav}>
                    <span className="shrink-0">{ICONS[item.iconKey]}</span>
                    {t(item.label)}
                  </NavLink>
                ))}
              </div>
            </div>
          ))}
        </nav>

        <div className="border-t border-side-border px-inline-sm py-stack-sm text-side-fg">
          <button
            type="button"
            onClick={openShortcutsOverlay}
            className="mb-stack-xs flex w-full items-center gap-inline-sm rounded-none px-inline-sm py-stack-xs text-body text-side-fg-muted transition-colors hover:bg-side-active/60 hover:text-side-fg"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              className="size-4.5 shrink-0"
              aria-hidden="true"
            >
              <rect x="2" y="5" width="16" height="10" rx="1.5" />
              <path d="M5 8h.01M8 8h.01M11 8h.01M14 8h.01M5 11h.01M14 11h.01M8 11h4" />
            </svg>
            <span className="flex-1 text-left">{t('admin.nav.shortcuts')}</span>
            <kbd className="kbd">?</kbd>
          </button>
          <AccountMenu />
        </div>
      </aside>

      <div className="side-backdrop" aria-hidden="true" onClick={closeNav} />

      <div className="app-main flex flex-col bg-surface">
        <header className="topbar flex items-center justify-between border-b border-border bg-surface-raised">
          <div className="tb-left flex items-center gap-inline-sm">
            <button
              type="button"
              className="tb-burger items-center justify-center border border-border-strong bg-surface-raised text-fg-muted"
              aria-label={t('admin.nav.openMenu')}
              onClick={() => {
                setNavOpen(true)
              }}
            >
              {burgerIcon}
            </button>
            <div className="tb-crumb text-body text-fg-muted">
              NeNe Invoice <span className="opacity-40">/</span>{' '}
              <span className="font-medium text-fg">
                {activeItem ? t(activeItem.label) : t('common.appName')}
              </span>
            </div>
          </div>
        </header>
        <main className="app-content w-full min-w-0 flex-1">
          <Outlet />
        </main>
      </div>

      <BottomNav
        pathname={pathname}
        menuActive={DRAWER_ROUTES.some(
          (route) => pathname === route || pathname.startsWith(`${route}/`),
        )}
        onMenu={() => {
          setNavOpen(true)
        }}
      />
    </div>
  )
}
