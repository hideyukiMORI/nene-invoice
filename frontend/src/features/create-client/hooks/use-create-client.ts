import { zodResolver } from '@hookform/resolvers/zod'
import type { SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useCreateClient as useCreateClientMutation } from '@/entities/client'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  name: z.string().min(1),
  contact_name: z.string(),
  email: z.string(),
  billing_address: z.string(),
  registration_number: z.string(),
})

export type CreateClientFormValues = z.infer<typeof schema>

export interface UseCreateClient {
  form: UseFormReturn<CreateClientFormValues>
  onSubmit: (event: SyntheticEvent) => void
  isPending: boolean
  errorMessage: string | null
}

const emptyToNull = (value: string): string | null => (value === '' ? null : value)

export function useCreateClient(): UseCreateClient {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateClientMutation()

  const form = useForm<CreateClientFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '',
      contact_name: '',
      email: '',
      billing_address: '',
      registration_number: '',
    },
  })

  const submit = form.handleSubmit((values) => {
    create.mutate(
      {
        name: values.name,
        contact_name: emptyToNull(values.contact_name),
        email: emptyToNull(values.email),
        billing_address: emptyToNull(values.billing_address),
        registration_number: emptyToNull(values.registration_number),
      },
      {
        onSuccess: () => {
          void navigate('/clients')
        },
      },
    )
  })

  return {
    form,
    onSubmit: (event) => {
      void submit(event)
    },
    isPending: create.isPending,
    errorMessage: create.isError ? t('admin.clients.create.error') : null,
  }
}
