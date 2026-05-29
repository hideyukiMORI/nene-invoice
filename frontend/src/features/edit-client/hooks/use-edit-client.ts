import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, type SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useClient, useUpdateClient, type ClientId } from '@/entities/client'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  name: z.string().min(1),
  contact_name: z.string(),
  email: z.string(),
  billing_address: z.string(),
  registration_number: z.string(),
})

export type EditClientFormValues = z.infer<typeof schema>

export type EditClientState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      form: UseFormReturn<EditClientFormValues>
      onSubmit: (event: SyntheticEvent) => void
      isPending: boolean
      errorMessage: string | null
    }

const emptyToNull = (value: string): string | null => (value === '' ? null : value)

export function useEditClient(clientId: ClientId): EditClientState {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const query = useClient(clientId)
  const update = useUpdateClient()

  const form = useForm<EditClientFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '',
      contact_name: '',
      email: '',
      billing_address: '',
      registration_number: '',
    },
  })

  // Prefill once the client loads (external sync → effect).
  const client = query.data
  const { reset } = form
  useEffect(() => {
    if (client !== undefined) {
      reset({
        name: client.name,
        contact_name: client.contact_name ?? '',
        email: client.email ?? '',
        billing_address: client.billing_address ?? '',
        registration_number: client.registration_number ?? '',
      })
    }
  }, [client, reset])

  if (query.isPending) {
    return { kind: 'loading' }
  }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }

  const submit = form.handleSubmit((values) => {
    update.mutate(
      {
        id: clientId,
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
    kind: 'ready',
    form,
    onSubmit: (event) => {
      void submit(event)
    },
    isPending: update.isPending,
    errorMessage: update.isError ? t('admin.clients.edit.error') : null,
  }
}
