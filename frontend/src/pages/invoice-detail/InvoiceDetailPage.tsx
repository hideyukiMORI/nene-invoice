import { Navigate, useParams } from 'react-router-dom'
import { toInvoiceId } from '@/entities/invoice'
import { ViewInvoice } from '@/features/view-invoice'

export function InvoiceDetailPage() {
  const { id } = useParams()
  const numericId = Number(id)

  if (id === undefined || !Number.isInteger(numericId) || numericId <= 0) {
    return <Navigate to="/invoices" replace />
  }

  return <ViewInvoice invoiceId={toInvoiceId(numericId)} />
}
