import { useNavigate } from 'react-router-dom'
import { useDownloadItemsImportTemplate, useImportItems } from '@/entities/item'
import { useTranslation } from '@/shared/i18n'
import { Button, CsvImportPanel, Stack, Text, useToast } from '@/shared/ui'

/** Template-only items import page (ADR 0011). Chrome here; mechanics in CsvImportPanel. */
export function ImportItems() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { showToast } = useToast()
  const template = useDownloadItemsImportTemplate()
  const importItems = useImportItems()

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.items.import.title')}
        </Text>
        <Button variant="ghost" size="sm" onClick={() => void navigate('/items')}>
          {t('admin.items.import.back')}
        </Button>
      </div>

      <Text variant="muted">{t('admin.items.import.lede')}</Text>

      <CsvImportPanel
        template={template}
        runImport={importItems}
        onDone={({ created, updated }) => {
          showToast({
            tone: 'ok',
            title: t('admin.items.import.doneTitle'),
            description: t('admin.items.import.doneBody', { created, updated }),
          })
          void navigate('/items')
        }}
      />
    </Stack>
  )
}
