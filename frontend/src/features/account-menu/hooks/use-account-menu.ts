import { useQueryClient } from '@tanstack/react-query'
import { signOut, useCurrentUser } from '@/entities/auth'

export interface UseAccountMenu {
  email: string | null
  onSignOut: () => void
}

/** Current-user summary plus sign-out (clears the session and query cache). */
export function useAccountMenu(): UseAccountMenu {
  const queryClient = useQueryClient()
  const me = useCurrentUser(true)

  return {
    email: me.data?.email ?? null,
    onSignOut: () => {
      signOut()
      queryClient.clear()
    },
  }
}
