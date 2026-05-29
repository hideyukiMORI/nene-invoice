import { zodResolver } from '@hookform/resolvers/zod'
import type { SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { z } from 'zod'
import { useLogin } from '@/entities/auth'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  email: z.email(),
  password: z.string().min(1),
})

export type SignInFormValues = z.infer<typeof schema>

export interface UseSignIn {
  form: UseFormReturn<SignInFormValues>
  onSubmit: (event: SyntheticEvent) => void
  isPending: boolean
  errorMessage: string | null
}

/** Composes the login mutation with client-side (UX) form validation. */
export function useSignIn(): UseSignIn {
  const { t } = useTranslation()
  const login = useLogin()
  const form = useForm<SignInFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  })

  const submit = form.handleSubmit((values) => {
    login.mutate(values)
  })

  const onSubmit = (event: SyntheticEvent): void => {
    void submit(event)
  }

  return {
    form,
    onSubmit,
    isPending: login.isPending,
    errorMessage: login.isError ? t('admin.auth.failed') : null,
  }
}
