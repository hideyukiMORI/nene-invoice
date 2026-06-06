import { Navigate, useParams } from 'react-router-dom'
import { toTemplateId } from '@/entities/template'
import { TemplateForm } from '@/features/template-form'

export function TemplateEditPage() {
  const { id } = useParams()
  const numericId = Number(id)

  if (id === undefined || !Number.isInteger(numericId) || numericId <= 0) {
    return <Navigate to="/templates" replace />
  }

  return <TemplateForm templateId={toTemplateId(numericId)} />
}
