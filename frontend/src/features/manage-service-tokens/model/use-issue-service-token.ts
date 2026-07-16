import { zodResolver } from '@hookform/resolvers/zod'
import type { SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { z } from 'zod'
import {
  useIssueServiceToken as useIssueServiceTokenMutation,
  type IssuedServiceToken,
} from '@/entities/service-token'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  label: z.string().trim().min(1).max(255),
  scopes: z.array(z.enum(['read:invoices', 'write:payments'])).min(1),
})

export type IssueServiceTokenFormValues = z.infer<typeof schema>

export interface UseIssueServiceToken {
  form: UseFormReturn<IssueServiceTokenFormValues>
  onSubmit: (event: SyntheticEvent) => void
  isPending: boolean
  errorMessage: string | null
}

/**
 * Issue-token form. On success it hands the one-time plaintext token to the
 * caller (which reveals it) and resets the form for the next issuance.
 */
export function useIssueServiceToken(
  onIssued: (token: IssuedServiceToken) => void,
): UseIssueServiceToken {
  const { t } = useTranslation()
  const issue = useIssueServiceTokenMutation()

  const form = useForm<IssueServiceTokenFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { label: '', scopes: ['read:invoices'] },
  })

  const submit = form.handleSubmit((values) => {
    issue.mutate(
      { label: values.label, scopes: values.scopes },
      {
        onSuccess: (token) => {
          onIssued(token)
          form.reset({ label: '', scopes: ['read:invoices'] })
        },
      },
    )
  })

  return {
    form,
    onSubmit: (event) => {
      void submit(event)
    },
    isPending: issue.isPending,
    errorMessage: issue.isError ? t('admin.serviceTokens.issue.error') : null,
  }
}
