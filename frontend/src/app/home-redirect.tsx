import { Navigate } from 'react-router-dom'
import { useCurrentUser } from '@/entities/auth'
import { useTranslation } from '@/shared/i18n'
import { Spinner } from '@/shared/ui'

/**
 * Role-aware landing. A superadmin is org-less (`organization_id` is null), so
 * the org-scoped dashboard and billing screens 404 for them — they land on
 * organization (tenant) management instead. Every other role lands on the
 * dashboard, unchanged.
 */
export function HomeRedirect() {
  const { t } = useTranslation()
  const me = useCurrentUser(true)

  if (me.isPending) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner label={t('admin.auth.restoringSession')} />
      </div>
    )
  }

  const target = me.data?.role === 'superadmin' ? '/organizations' : '/dashboard'
  return <Navigate to={target} replace />
}
