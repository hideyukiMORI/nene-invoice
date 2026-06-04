import {
  useInvoice,
  useIssueInvoice as useIssueInvoiceMutation,
  type InvoiceId,
} from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { useToast } from '@/shared/ui'

export interface UseIssueInvoice {
  /** Only draft invoices can be issued; the action hides otherwise. */
  canIssue: boolean
  issue: () => void
  isPending: boolean
  errorMessage: string | null
}

/**
 * Issues the invoice as a qualified invoice. Reads the (cached) invoice to gate
 * the action on draft status — shares the detail query, so no extra fetch.
 */
export function useIssueInvoice(invoiceId: InvoiceId): UseIssueInvoice {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const invoice = useInvoice(invoiceId)
  const mutation = useIssueInvoiceMutation()

  return {
    canIssue: invoice.data?.status === 'draft',
    issue: () => {
      mutation.mutate(
        { id: invoiceId, qualified: true, due_at: null },
        {
          onSuccess: (issued) => {
            showToast({
              tone: 'ok',
              title: t('admin.invoices.issue.successTitle'),
              description:
                issued.invoice_number !== null
                  ? t('admin.invoices.issue.successBody', { number: issued.invoice_number })
                  : t('admin.invoices.issue.successBodyNoNumber'),
            })
          },
        },
      )
    },
    isPending: mutation.isPending,
    errorMessage: mutation.isError ? t('admin.invoices.issue.error') : null,
  }
}
