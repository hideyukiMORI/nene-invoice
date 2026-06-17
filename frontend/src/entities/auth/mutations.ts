import { useMutation, type UseMutationResult } from '@tanstack/react-query'
import { apiClient, revokeSession, setAuthToken } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { LoginResponseDto } from './api-types'
import type { Credentials } from './model'

/**
 * POST /auth/login — verifies credentials and stores the bearer token in memory
 * (see frontend-standards: in-memory by default). On success the session is active.
 */
export function useLogin(): UseMutationResult<string, AppError, Credentials> {
  return useMutation<string, AppError, Credentials>({
    mutationFn: async (credentials) => {
      const result = await apiClient.post<LoginResponseDto>('/auth/login', {
        email: credentials.email,
        password: credentials.password,
      })
      setAuthToken(result.token)
      return result.token
    },
  })
}

/**
 * Signs out (ADR 0014): fires a best-effort server-side revocation of the
 * refresh-token family (and cookie clear), then clears the in-memory token
 * immediately so the fail-closed auth shell shows login without waiting on the
 * network.
 */
export function signOut(): void {
  void revokeSession()
  setAuthToken(null)
}
