import { Navigate, useParams } from 'react-router-dom'
import { toClientId } from '@/entities/client'
import { EditClient } from '@/features/edit-client'

export function ClientEditPage() {
  const { id } = useParams()
  const numericId = Number(id)

  if (id === undefined || !Number.isInteger(numericId) || numericId <= 0) {
    return <Navigate to="/clients" replace />
  }

  return <EditClient clientId={toClientId(numericId)} />
}
