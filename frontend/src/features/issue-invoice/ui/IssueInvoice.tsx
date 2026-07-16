import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import type { InvoiceId } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { Button, ConfirmDialog, InlineAlert, Stack } from '@/shared/ui'
import { useIssueInvoice } from '../model/use-issue-invoice'

export interface IssueInvoiceProps {
  invoiceId: InvoiceId
}

/** Issue action — renders only for a draft invoice. Issuing is irreversible
 * (allocates the INV number and locks the document), so it is confirmed. */
export function IssueInvoice({ invoiceId }: IssueInvoiceProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { canIssue, issue, isPending, errorMessage } = useIssueInvoice(invoiceId)
  const [confirming, setConfirming] = useState(false)

  if (!canIssue) {
    return null
  }

  const onConfirm = (): void => {
    setConfirming(false)
    issue()
  }

  return (
    <Stack gap="sm">
      <div>
        <Button
          onClick={() => {
            setConfirming(true)
          }}
          disabled={isPending}
        >
          {isPending ? t('admin.invoices.issue.submitting') : t('admin.invoices.issue.action')}
        </Button>
      </div>
      {errorMessage !== null && (
        <div className="max-w-xl">
          <InlineAlert
            tone="error"
            message={errorMessage}
            recover={{
              label: t('admin.invoices.issue.recover'),
              onClick: () => {
                void navigate('/settings')
              },
            }}
          />
        </div>
      )}
      {confirming && (
        <ConfirmDialog
          title={t('admin.invoices.issue.confirmTitle')}
          message={t('admin.invoices.issue.confirmMessage')}
          confirmLabel={t('admin.invoices.issue.action')}
          cancelLabel={t('common.actions.cancel')}
          destructive={false}
          pending={isPending}
          onConfirm={onConfirm}
          onCancel={() => {
            setConfirming(false)
          }}
        />
      )}
    </Stack>
  )
}
