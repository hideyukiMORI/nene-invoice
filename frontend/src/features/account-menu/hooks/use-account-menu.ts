import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { signOut, useCurrentUser, type Role } from '@/entities/auth'

export interface UseAccountMenu {
  email: string | null
  role: Role | null
  onSignOut: () => void
}

/** Current-user summary plus sign-out (clears the session and query cache). */
export function useAccountMenu(): UseAccountMenu {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const me = useCurrentUser(true)

  return {
    email: me.data?.email ?? null,
    role: me.data?.role ?? null,
    onSignOut: () => {
      // Reset the URL to the app root before clearing the session (#654). The
      // fail-closed AuthGate renders the login screen in place on token loss
      // (ADR 0014 / vault #168) — great for an unexpected 401, but on an
      // explicit sign-out it would leave the deep admin path (e.g.
      // /audit-logs) in the address bar. Navigating to '/' first cleans the URL
      // and lands the next login on the dashboard, matching clear/vault.
      void navigate('/', { replace: true })
      signOut()
      queryClient.clear()
    },
  }
}
