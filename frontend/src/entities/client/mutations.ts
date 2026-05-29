import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ClientDto } from './api-types'
import { toClient } from './mapper'
import type { Client, CreateClientInput } from './model'
import { clientKeys } from './query-keys'

/** POST /admin/clients — creates a client; invalidates the client lists on success. */
export function useCreateClient(): UseMutationResult<Client, AppError, CreateClientInput> {
  const queryClient = useQueryClient()

  return useMutation<Client, AppError, CreateClientInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<ClientDto>('/admin/clients', {
        name: input.name,
        contact_name: input.contact_name,
        email: input.email,
        billing_address: input.billing_address,
        registration_number: input.registration_number,
      })
      return toClient(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: clientKeys.lists() })
    },
  })
}
