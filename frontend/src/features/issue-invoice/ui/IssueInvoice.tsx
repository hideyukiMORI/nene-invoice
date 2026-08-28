import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import type { InvoiceId } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { Button, ConfirmDialog, InlineAlert, Stack } from '@/shared/ui'
import { useIssueInvoice } from '../hooks/use-issue-invoice'

export interface IssueInvoiceProps {
  invoiceId: InvoiceId
}

/** Issue action — renders only for a draft invoice. Issuing is irreversible
 * (allocates the INV number and locks the document), so it is confirmed.
 *
 * 🔴 The 適格請求書 wording is ADDITIVE, never the fallback (#771). The plain
 * label is what renders while the issuer's eligibility is still unknown, and
 * the qualifier is appended only once `qualified === true`. The two mistakes
 * are not symmetric: a button that says 適格請求書 before we have measured the
 * registration number makes a tax claim on the operator's behalf, while a
 * button that says 発行する understates one. Only the second is recoverable. */
export function IssueInvoice({ invoiceId }: IssueInvoiceProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { canIssue, qualified, issue, isPending, errorMessage } = useIssueInvoice(invoiceId)
  const [confirming, setConfirming] = useState(false)

  if (!canIssue) {
    return null
  }

  const actionLabel =
    qualified === true
      ? t('admin.invoices.issue.actionQualified')
      : t('admin.invoices.issue.action')

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
          disabled={isPending || qualified === null}
        >
          {isPending ? t('admin.invoices.issue.submitting') : actionLabel}
        </Button>
      </div>
      {errorMessage !== null && (
        <div className="max-w-xl">
          <InlineAlert
            tone="error"
            message={errorMessage}
            /* The settings link is the recovery for a MISSING registration
               number. An unqualified issue cannot fail that way, so pointing
               there would send the operator to fix something that is not
               broken — and imply the T-number is required to invoice at all,
               which is the misreading #771 was opened about. */
            {...(qualified === false
              ? {}
              : {
                  recover: {
                    label: t('admin.invoices.issue.recover'),
                    onClick: () => {
                      void navigate('/settings')
                    },
                  },
                })}
          />
        </div>
      )}
      {confirming && (
        <ConfirmDialog
          title={t('admin.invoices.issue.confirmTitle')}
          message={
            qualified === false
              ? t('admin.invoices.issue.confirmMessageUnqualified')
              : t('admin.invoices.issue.confirmMessage')
          }
          confirmLabel={actionLabel}
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
