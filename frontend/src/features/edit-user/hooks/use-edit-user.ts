import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, type SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useUser, useUpdateUser, type UserId } from '@/entities/user'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  email: z.email(),
  password: z.string(),
  role: z.enum(['admin', 'member', 'viewer']),
})

export type EditUserFormValues = z.infer<typeof schema>

export type EditUserState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      form: UseFormReturn<EditUserFormValues>
      onSubmit: (event: SyntheticEvent) => void
      isPending: boolean
      errorMessage: string | null
    }

export function useEditUser(userId: UserId): EditUserState {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const query = useUser(userId)
  const update = useUpdateUser()

  const form = useForm<EditUserFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '', role: 'member' },
  })

  const user = query.data
  const { reset } = form
  useEffect(() => {
    if (user !== undefined) {
      const role = user.role === 'superadmin' ? 'admin' : user.role
      reset({ email: user.email, password: '', role })
    }
  }, [user, reset])

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
    if (user === undefined) {
      return
    }
    update.mutate(
      {
        id: userId,
        email: values.email,
        password: values.password !== '' ? values.password : undefined,
        role: values.role,
        // The API requires status; the edit screen has no status control, so
        // resend the current value unchanged (#622).
        status: user.status,
      },
      {
        onSuccess: () => {
          void navigate('/users')
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
    errorMessage: update.isError ? t('admin.users.edit.error') : null,
  }
}
