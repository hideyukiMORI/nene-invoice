import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import type { SyntheticEvent } from 'react'
import { useState } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { z } from 'zod'
import { invoiceKeys, useInvoice, type InvoiceId } from '@/entities/invoice'
import { usePaymentList, useRecordPayment, type Payment } from '@/entities/payment'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'

export const PAYMENT_METHODS = ['bank_transfer', 'cash', 'other'] as const

const schema = z.object({
  amount_cents: z.number().int().min(1),
  method: z.enum(['', 'bank_transfer', 'cash', 'other']),
  note: z.string(),
})

export type RecordPaymentFormValues = z.infer<typeof schema>

export interface UseManagePayments {
  /** Hidden entirely for drafts (no liability yet). */
  visible: boolean
  /** Issued / partially_paid invoices still accept payments. */
  canRecord: boolean
  payments: Payment[]
  totalPaidCents: number
  paymentsLoading: boolean
  paymentsError: boolean
  form: UseFormReturn<RecordPaymentFormValues>
  onSubmit: (event: SyntheticEvent) => void
  /** Truthy while the confirm dialog is open. */
  confirming: boolean
  confirmTitle: string
  onConfirm: () => void
  onCancel: () => void
  isRecording: boolean
  errorMessage: string | null
}

export function useManagePayments(invoiceId: InvoiceId): UseManagePayments {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invoice = useInvoice(invoiceId)
  const payments = usePaymentList(invoiceId)
  const record = useRecordPayment()

  const form = useForm<RecordPaymentFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { amount_cents: 0, method: '', note: '' },
  })

  const [pending, setPending] = useState<RecordPaymentFormValues | null>(null)

  const status = invoice.data?.status

  const submit = form.handleSubmit((values) => {
    setPending(values)
  })

  const onConfirm = (): void => {
    if (!pending) return
    const values = pending
    setPending(null)
    record.mutate(
      {
        invoice_id: invoiceId,
        amount_cents: values.amount_cents,
        method: values.method === '' ? null : values.method,
        note: values.note === '' ? null : values.note,
      },
      {
        onSuccess: () => {
          form.reset({ amount_cents: 0, method: '', note: '' })
          void queryClient.invalidateQueries({ queryKey: invoiceKeys.detail(invoiceId) })
          void queryClient.invalidateQueries({ queryKey: invoiceKeys.lists() })
        },
      },
    )
  }

  const onCancel = (): void => {
    setPending(null)
  }

  const confirmTitle = t('admin.payments.record.confirmTitle', {
    amount: formatYen(pending?.amount_cents ?? 0),
  })

  return {
    visible: status !== undefined && status !== 'draft',
    canRecord: status === 'issued' || status === 'partially_paid',
    payments: payments.data?.items ?? [],
    totalPaidCents: payments.data?.total_paid_cents ?? 0,
    paymentsLoading: payments.isPending,
    paymentsError: payments.isError,
    form,
    onSubmit: (event) => {
      void submit(event)
    },
    confirming: pending !== null,
    confirmTitle,
    onConfirm,
    onCancel,
    isRecording: record.isPending,
    errorMessage: record.isError ? t('admin.payments.record.error') : null,
  }
}
