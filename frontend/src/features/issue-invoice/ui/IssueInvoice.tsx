import type { InvoiceId } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { Button, Stack, Text } from '@/shared/ui'
import { useIssueInvoice } from '../hooks/use-issue-invoice'

export interface IssueInvoiceProps {
  invoiceId: InvoiceId
}

/** Issue action — renders only for a draft invoice. */
export function IssueInvoice({ invoiceId }: IssueInvoiceProps) {
  const { t } = useTranslation()
  const { canIssue, issue, isPending, errorMessage } = useIssueInvoice(invoiceId)

  if (!canIssue) {
    return null
  }

  return (
    <Stack gap="sm">
      <div>
        <Button onClick={issue} disabled={isPending}>
          {isPending ? t('admin.invoices.issue.submitting') : t('admin.invoices.issue.action')}
        </Button>
      </div>
      {errorMessage !== null && (
        <Text variant="muted" role="alert">
          {errorMessage}
        </Text>
      )}
    </Stack>
  )
}
