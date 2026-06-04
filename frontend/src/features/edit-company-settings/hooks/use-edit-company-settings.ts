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
  // Billing defaults (Issue #268). Kept as strings in the form; '' = unset.
  // For closing/pay day, '' = 末日; for month offset, '' = no payment-terms default.
  default_quote_validity_days: z.string(),
  default_payment_closing_day: z.string(),
  default_payment_month_offset: z.string(),
  default_payment_pay_day: z.string(),
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
const numberToEmpty = (v: number | null | undefined): string =>
  v === null || v === undefined ? '' : String(v)

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
      default_quote_validity_days: '',
      default_payment_closing_day: '',
      default_payment_month_offset: '',
      default_payment_pay_day: '',
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
        default_quote_validity_days: numberToEmpty(settings?.default_quote_validity_days),
        default_payment_closing_day: numberToEmpty(settings?.default_payment_closing_day),
        default_payment_month_offset: numberToEmpty(settings?.default_payment_month_offset),
        default_payment_pay_day: numberToEmpty(settings?.default_payment_pay_day),
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
  const intOrNull = (v: string): number | null => (v === '' ? null : Number.parseInt(v, 10))

  const submit = form.handleSubmit((values) => {
    setSavedMessage(null)
    // Payment terms are active only when a month offset is chosen; otherwise the
    // whole 締め日＋支払サイト default is cleared.
    const monthOffset = intOrNull(values.default_payment_month_offset)
    const termsActive = monthOffset !== null
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
        default_quote_validity_days: intOrNull(values.default_quote_validity_days),
        default_payment_closing_day: termsActive
          ? intOrNull(values.default_payment_closing_day)
          : null,
        default_payment_month_offset: monthOffset,
        default_payment_pay_day: termsActive ? intOrNull(values.default_payment_pay_day) : null,
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
