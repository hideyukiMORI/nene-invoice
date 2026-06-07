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
    <Stack gap="md" className="csv-import">
      <div className="page-head">
        <div>
          <div className="crumb">{t('admin.items.import.crumb')}</div>
          <Text as="h1" variant="heading-md">
            {t('admin.items.import.title')}
          </Text>
        </div>
        <Button
          variant="ghost"
          size="sm"
          className="gap-inline-xs"
          onClick={() => void navigate('/items')}
        >
          <svg
            width={15}
            height={15}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            aria-hidden="true"
          >
            <path d="M15 18l-6-6 6-6" />
          </svg>
          {t('admin.items.import.back')}
        </Button>
      </div>

      <p className="lede">{t('admin.items.import.lede')}</p>

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
