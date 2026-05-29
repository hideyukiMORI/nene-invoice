import { useMutation, type UseMutationResult } from '@tanstack/react-query'
import { apiClient, setAuthToken } from '@/shared/api/client'
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

/** Clears the in-memory session; the auth shell observes this and shows login. */
export function signOut(): void {
  setAuthToken(null)
}
