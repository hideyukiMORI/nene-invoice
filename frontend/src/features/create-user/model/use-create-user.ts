import { zodResolver } from '@hookform/resolvers/zod'
import type { SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useCreateUser as useCreateUserMutation } from '@/entities/user'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  email: z.email(),
  password: z.string().min(8),
  role: z.enum(['admin', 'member', 'viewer']),
})

export type CreateUserFormValues = z.infer<typeof schema>

export interface UseCreateUser {
  form: UseFormReturn<CreateUserFormValues>
  onSubmit: (event: SyntheticEvent) => void
  isPending: boolean
  errorMessage: string | null
}

export function useCreateUser(): UseCreateUser {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateUserMutation()

  const form = useForm<CreateUserFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '', role: 'member' },
  })

  const submit = form.handleSubmit((values) => {
    create.mutate(
      { email: values.email, password: values.password, role: values.role },
      {
        onSuccess: () => {
          void navigate('/users')
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
    errorMessage: create.isError ? t('admin.users.create.error') : null,
  }
}
