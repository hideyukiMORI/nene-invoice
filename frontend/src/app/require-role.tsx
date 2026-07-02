import { Navigate, Outlet } from 'react-router-dom'
import { useCurrentUser } from '@/entities/auth'
import { useTranslation } from '@/shared/i18n'
import { Spinner } from '@/shared/ui'

/**
 * Route audiences (型B Phase 2). `org` routes are org-scoped (billing/dashboard)
 * and need a tenant context; `superadmin` routes manage organizations
 * cross-tenant.
 */
type Audience = 'org' | 'superadmin'

/**
 * Per-route role guard. A superadmin is org-less (cross-tenant host), so every
 * org-scoped screen 404s for them — they are redirected to organization
 * management; a tenant operator (admin/member/viewer) hitting the
 * superadmin-only organization routes is redirected to their dashboard.
 *
 * This complements {@link AppShell}'s role-aware nav (which only hides links) by
 * catching manually-typed or deep-linked URLs. It is UX only: the backend RBAC
 * (capabilities) and {@link OrgGuardMiddleware} remain the real enforcement
 * (ADR 0006) — this keeps operators off pages that would 404/403 for their role.
 */
export function RequireRole({ audience }: { audience: Audience }) {
  const { t } = useTranslation()
  const me = useCurrentUser(true)

  if (me.isPending) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner label={t('admin.auth.restoringSession')} />
      </div>
    )
  }

  const isSuperadmin = me.data?.role === 'superadmin'

  if (audience === 'superadmin' && !isSuperadmin) {
    return <Navigate to="/dashboard" replace />
  }

  if (audience === 'org' && isSuperadmin) {
    return <Navigate to="/organizations" replace />
  }

  return <Outlet />
}
