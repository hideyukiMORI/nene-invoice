import { Navigate, useParams } from 'react-router-dom'
import { toInvoiceId } from '@/entities/invoice'
import { IssueInvoice } from '@/features/issue-invoice'
import { ManagePayments } from '@/features/manage-payments'
import { ViewInvoice } from '@/features/view-invoice'
import { Stack } from '@/shared/ui'

export function InvoiceDetailPage() {
  const { id } = useParams()
  const numericId = Number(id)

  if (id === undefined || !Number.isInteger(numericId) || numericId <= 0) {
    return <Navigate to="/invoices" replace />
  }

  const invoiceId = toInvoiceId(numericId)

  return (
    <Stack gap="lg">
      <ViewInvoice invoiceId={invoiceId} />
      <IssueInvoice invoiceId={invoiceId} />
      <ManagePayments invoiceId={invoiceId} />
    </Stack>
  )
}
