import { Navigate, useParams } from 'react-router-dom'
import { toItemId } from '@/entities/item'
import { EditItem } from '@/features/edit-item'

export function ItemEditPage() {
  const { id } = useParams()
  const numericId = Number(id)

  if (id === undefined || !Number.isInteger(numericId) || numericId <= 0) {
    return <Navigate to="/items" replace />
  }

  return <EditItem itemId={toItemId(numericId)} />
}
