import { useRef, type ChangeEvent } from 'react'
import { useTranslation } from '@/shared/i18n'
import { Button, InlineAlert, LoadingState, Stack, Text } from '@/shared/ui'
import { useManageCompanySeal } from '../model/use-manage-company-seal'

/** Company seal (社印) upload widget — preview, replace, remove (Issue #448). */
export function ManageCompanySeal() {
  const { t } = useTranslation()
  const state = useManageCompanySeal()
  const inputRef = useRef<HTMLInputElement>(null)

  const onChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const file = event.target.files?.[0]
    if (file !== undefined) {
      state.onSelectFile(file)
    }
    // Allow re-selecting the same file after an error.
    event.target.value = ''
  }

  return (
    <div className="card">
      <div className="section-title">{t('admin.settings.seal.title')}</div>
      <Stack gap="md">
        <Text variant="muted" className="text-caption">
          {t('admin.settings.seal.hint')}
        </Text>

        {state.isLoading ? (
          <LoadingState message={t('admin.settings.seal.loading')} />
        ) : (
          <Stack gap="md">
            {state.previewDataUri !== null && (
              <img
                src={state.previewDataUri}
                alt={t('admin.settings.seal.previewAlt')}
                className="seal-preview"
                width={96}
                height={96}
              />
            )}

            {state.errorMessage !== null && (
              <InlineAlert tone="error" message={state.errorMessage} />
            )}

            <input
              ref={inputRef}
              type="file"
              accept="image/png"
              onChange={onChange}
              style={{ display: 'none' }}
              data-testid="seal-file-input"
            />
            <div className="seal-actions">
              <Button
                type="button"
                disabled={state.isUploading}
                onClick={() => inputRef.current?.click()}
              >
                {state.isUploading
                  ? t('admin.settings.seal.uploading')
                  : state.hasSeal
                    ? t('admin.settings.seal.replace')
                    : t('admin.settings.seal.upload')}
              </Button>
              {state.hasSeal && (
                <Button
                  type="button"
                  variant="ghost"
                  disabled={state.isRemoving}
                  onClick={state.onRemove}
                >
                  {state.isRemoving
                    ? t('admin.settings.seal.removing')
                    : t('admin.settings.seal.remove')}
                </Button>
              )}
            </div>
          </Stack>
        )}
      </Stack>
    </div>
  )
}
