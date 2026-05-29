import { useSyncExternalStore, type ReactNode } from 'react'
import { hasAuthToken, subscribeAuthChange } from '@/shared/api/client'
import { LoginPage } from '@/pages/login'

/**
 * Fail-closed auth shell. Reactively gates on the in-memory token: no token →
 * login screen; a successful login flips the store and reveals the app. A 401
 * elsewhere clears the token (sign-out), which lands the user back here.
 */
export function AuthGate({ children }: { children: ReactNode }) {
  const authed = useSyncExternalStore(subscribeAuthChange, hasAuthToken)

  if (!authed) {
    return <LoginPage />
  }

  return children
}
