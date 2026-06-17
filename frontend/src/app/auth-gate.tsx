import { useEffect, useState, useSyncExternalStore, type ReactNode } from 'react'
import { hasAuthToken, refreshSession, subscribeAuthChange } from '@/shared/api/client'
import { useTranslation } from '@/shared/i18n'
import { Spinner } from '@/shared/ui'
import { LoginPage } from '@/pages/login'

/**
 * Fail-closed auth shell. Reactively gates on the in-memory token: no token →
 * login screen; a successful login flips the store and reveals the app. A 401
 * elsewhere clears the token (sign-out), which lands the user back here.
 *
 * On first mount with no token (e.g. after a full page reload) it attempts one
 * silent refresh via the httpOnly refresh cookie (ADR 0014) before deciding. A
 * neutral splash covers that probe so an authenticated operator never flashes
 * the login screen on reload; a failed probe falls through to login as before.
 */
export function AuthGate({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const authed = useSyncExternalStore(subscribeAuthChange, hasAuthToken)
  // Skip the probe (and splash) when a token is already in memory.
  const [probed, setProbed] = useState(() => hasAuthToken())

  useEffect(() => {
    if (hasAuthToken()) return
    let active = true
    void refreshSession().finally(() => {
      if (active) setProbed(true)
    })
    return () => {
      active = false
    }
  }, [])

  if (!authed && !probed) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner label={t('admin.auth.restoringSession')} />
      </div>
    )
  }

  if (!authed) {
    return <LoginPage />
  }

  return children
}
