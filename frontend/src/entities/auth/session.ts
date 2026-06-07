import { useSyncExternalStore } from 'react'
import { subscribeAuthChange, wasSessionExpired } from '@/shared/api/client'

/**
 * True when the session ended because a request returned 401 (expired/invalid
 * token) rather than the user never having signed in. Lets the login screen
 * explain why it appeared. Cleared on the next successful sign-in.
 */
export function useSessionExpired(): boolean {
  return useSyncExternalStore(subscribeAuthChange, wasSessionExpired)
}
