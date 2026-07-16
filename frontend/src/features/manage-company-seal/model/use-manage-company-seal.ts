import { useState } from 'react'
import { useCompanySeal, useDeleteCompanySeal, useUploadCompanySeal } from '@/entities/company-seal'
import { useTranslation } from '@/shared/i18n'

/** Client-side cap, mirrors the backend SealImageValidator (512 KB). */
const MAX_BYTES = 512 * 1024

export interface ManageCompanySealState {
  isLoading: boolean
  hasSeal: boolean
  /** `data:image/png;base64,...` for an <img>, or null when no seal is set. */
  previewDataUri: string | null
  onSelectFile: (file: File) => void
  onRemove: () => void
  isUploading: boolean
  isRemoving: boolean
  errorMessage: string | null
}

export function useManageCompanySeal(): ManageCompanySealState {
  const { t } = useTranslation()
  const query = useCompanySeal()
  const upload = useUploadCompanySeal()
  const remove = useDeleteCompanySeal()
  const [localError, setLocalError] = useState<string | null>(null)

  const onSelectFile = (file: File): void => {
    setLocalError(null)

    if (file.type !== 'image/png') {
      setLocalError(t('admin.settings.seal.errorType'))
      return
    }
    if (file.size > MAX_BYTES) {
      setLocalError(t('admin.settings.seal.errorSize'))
      return
    }

    const reader = new FileReader()
    reader.onerror = () => {
      setLocalError(t('admin.settings.seal.errorRead'))
    }
    reader.onload = () => {
      const result = typeof reader.result === 'string' ? reader.result : ''
      const base64 = result.split(',')[1] ?? ''
      if (base64 === '') {
        setLocalError(t('admin.settings.seal.errorRead'))
        return
      }
      upload.mutate(base64)
    }
    reader.readAsDataURL(file)
  }

  const onRemove = (): void => {
    setLocalError(null)
    remove.mutate()
  }

  const seal = query.data
  const previewDataUri =
    seal?.has_seal === true && seal.image_base64 !== null
      ? `data:image/png;base64,${seal.image_base64}`
      : null

  const serverError = upload.isError || remove.isError ? t('admin.settings.seal.errorUpload') : null

  return {
    isLoading: query.isPending,
    hasSeal: seal?.has_seal ?? false,
    previewDataUri,
    onSelectFile,
    onRemove,
    isUploading: upload.isPending,
    isRemoving: remove.isPending,
    errorMessage: localError ?? serverError,
  }
}
