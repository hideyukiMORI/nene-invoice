import type { InvoiceId } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import {
  Button,
  ConfirmDialog,
  EmptyState,
  Field,
  Input,
  Select,
  Spinner,
  Stack,
  Text,
} from '@/shared/ui'
import { PAYMENT_METHODS, useManagePayments } from '../hooks/use-manage-payments'

export interface ManagePaymentsProps {
  invoiceId: InvoiceId
}

/** Payment list + record form for an issued invoice. Hidden for drafts. */
export function ManagePayments({ invoiceId }: ManagePaymentsProps) {
  const { t } = useTranslation()
  const {
    visible,
    canRecord,
    payments,
    totalPaidCents,
    paymentsLoading,
    form,
    onSubmit,
    confirming,
    confirmTitle,
    onConfirm,
    onCancel,
    isRecording,
    errorMessage,
  } = useManagePayments(invoiceId)
  const {
    register,
    formState: { errors },
  } = form

  if (!visible) {
    return null
  }

  return (
    <Stack gap="md">
      <Text as="h2" variant="heading-sm">
        {t('admin.payments.title')}
      </Text>

      {paymentsLoading && (
        <Stack direction="row" gap="sm">
          <Spinner label={t('admin.payments.loading')} />
          <Text variant="muted">{t('admin.payments.loading')}</Text>
        </Stack>
      )}

      {!paymentsLoading && payments.length === 0 && (
        <EmptyState message={t('admin.payments.empty')} />
      )}

      {payments.length > 0 && (
        <table className="w-full border-collapse text-body">
          <thead>
            <tr className="border-b border-border text-left">
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.payments.col.paidAt')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.payments.col.method')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.payments.col.note')}
              </th>
              <th className="py-stack-sm text-right font-medium">
                {t('admin.payments.col.amount')}
              </th>
            </tr>
          </thead>
          <tbody>
            {payments.map((payment) => (
              <tr key={payment.id} className="border-b border-border">
                <td className="py-stack-sm pr-inline-md">{payment.paid_at}</td>
                <td className="py-stack-sm pr-inline-md">
                  {payment.method === null ? '—' : t(`admin.payments.method.${payment.method}`)}
                </td>
                <td className="py-stack-sm pr-inline-md">{payment.note ?? '—'}</td>
                <td className="py-stack-sm text-right">{formatYen(payment.amount_cents)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      <div className="flex justify-between">
        <Text variant="muted">{t('admin.payments.totalPaid')}</Text>
        <Text>{formatYen(totalPaidCents)}</Text>
      </div>

      {canRecord && (
        <form onSubmit={onSubmit} noValidate>
          <Stack gap="sm">
            <Text variant="heading-sm">{t('admin.payments.record.title')}</Text>
            <Stack direction="row" gap="sm">
              <Field
                id="payment-amount"
                label={t('admin.payments.record.amount')}
                error={errors.amount_cents ? t('admin.payments.record.invalid') : undefined}
              >
                <Input
                  id="payment-amount"
                  type="number"
                  min={1}
                  aria-invalid={errors.amount_cents ? true : undefined}
                  {...register('amount_cents', { valueAsNumber: true })}
                />
              </Field>
              <Field id="payment-method" label={t('admin.payments.record.method')}>
                <Select id="payment-method" {...register('method')}>
                  <option value="">—</option>
                  {PAYMENT_METHODS.map((method) => (
                    <option key={method} value={method}>
                      {t(`admin.payments.method.${method}`)}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field id="payment-note" label={t('admin.payments.record.note')}>
                <Input id="payment-note" {...register('note')} />
              </Field>
            </Stack>
            {errorMessage !== null && (
              <Text variant="muted" role="alert">
                {errorMessage}
              </Text>
            )}
            <div>
              <Button type="submit" disabled={isRecording}>
                {isRecording
                  ? t('admin.payments.record.submitting')
                  : t('admin.payments.record.submit')}
              </Button>
            </div>
          </Stack>
        </form>
      )}
      {confirming && (
        <ConfirmDialog
          title={confirmTitle}
          message={t('admin.payments.record.confirmMessage')}
          confirmLabel={t('admin.payments.record.submit')}
          cancelLabel={t('common.actions.cancel')}
          destructive={false}
          pending={isRecording}
          onConfirm={onConfirm}
          onCancel={onCancel}
        />
      )}
    </Stack>
  )
}
