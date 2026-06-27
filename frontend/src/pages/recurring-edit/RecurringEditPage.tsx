import { Navigate, useParams } from 'react-router-dom'
import { toRecurringInvoiceId } from '@/entities/recurring-invoice'
import { EditRecurringInvoice } from '@/features/edit-recurring-invoice'

export function RecurringEditPage() {
  const { id } = useParams()
  const numericId = Number(id)

  if (id === undefined || !Number.isInteger(numericId) || numericId <= 0) {
    return <Navigate to="/recurring" replace />
  }

  return <EditRecurringInvoice recurringInvoiceId={toRecurringInvoiceId(numericId)} />
}
