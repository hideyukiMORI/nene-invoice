import { Navigate, useParams } from 'react-router-dom'
import { toUserId } from '@/entities/user'
import { EditUser } from '@/features/edit-user'

export function UserEditPage() {
  const { id } = useParams()
  const numericId = Number(id)

  if (id === undefined || !Number.isInteger(numericId) || numericId <= 0) {
    return <Navigate to="/users" replace />
  }

  return <EditUser userId={toUserId(numericId)} />
}
