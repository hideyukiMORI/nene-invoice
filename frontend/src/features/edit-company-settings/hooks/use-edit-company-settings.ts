import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState, type SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { z } from 'zod'
import { useCompanySettings, useUpdateCompanySettings } from '@/entities/company-settings'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  legal_name: z.string().min(1),
  address: z.string(),
  phone: z.string(),
  email: z.string(),
  registration_number: z.string(),
  bank_name: z.string(),
  bank_branch: z.string(),
  account_type: z.string(),
  account_number: z.string(),
})

export type EditCompanySettingsFormValues = z.infer<typeof schema>

export type EditCompanySettingsState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      form: UseFormReturn<EditCompanySettingsFormValues>
      onSubmit: (event: SyntheticEvent) => void
      isPending: boolean
      errorMessage: string | null
      savedMessage: string | null
    }

const nullToEmpty = (v: string | null | undefined): string => v ?? ''

export function useEditCompanySettings(): EditCompanySettingsState {
  const { t } = useTranslation()
  const query = useCompanySettings()
  const update = useUpdateCompanySettings()
  const [savedMessage, setSavedMessage] = useState<string | null>(null)

  const form = useForm<EditCompanySettingsFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      legal_name: '',
      address: '',
      phone: '',
      email: '',
      registration_number: '',
      bank_name: '',
      bank_branch: '',
      account_type: '',
      account_number: '',
    },
  })

  const settings = query.data
  const { reset } = form
  useEffect(() => {
    if (settings !== undefined) {
      reset({
        legal_name: nullToEmpty(settings?.legal_name),
        address: nullToEmpty(settings?.address),
        phone: nullToEmpty(settings?.phone),
        email: nullToEmpty(settings?.email),
        registration_number: nullToEmpty(settings?.registration_number),
        bank_name: nullToEmpty(settings?.bank_name),
        bank_branch: nullToEmpty(settings?.bank_branch),
        account_type: nullToEmpty(settings?.account_type),
        account_number: nullToEmpty(settings?.account_number),
      })
    }
  }, [settings, reset])

  if (query.isPending) return { kind: 'loading' }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }

  const emptyToNull = (v: string): string | null => (v === '' ? null : v)

  const submit = form.handleSubmit((values) => {
    setSavedMessage(null)
    update.mutate(
      {
        legal_name: values.legal_name,
        address: emptyToNull(values.address),
        phone: emptyToNull(values.phone),
        email: emptyToNull(values.email),
        registration_number: emptyToNull(values.registration_number),
        bank_name: emptyToNull(values.bank_name),
        bank_branch: emptyToNull(values.bank_branch),
        account_type: emptyToNull(values.account_type),
        account_number: emptyToNull(values.account_number),
      },
      {
        onSuccess: () => {
          setSavedMessage(t('admin.settings.savedMessage'))
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
    errorMessage: update.isError ? t('admin.settings.error') : null,
    savedMessage,
  }
}
