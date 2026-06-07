import { useNavigate } from 'react-router-dom'
import { useDownloadClientsImportTemplate, useImportClients } from '@/entities/client'
import { useTranslation } from '@/shared/i18n'
import { Button, CsvImportPanel, Stack, Text, useToast } from '@/shared/ui'

/** Template-only clients import page (ADR 0011). Chrome here; mechanics in CsvImportPanel. */
export function ImportClients() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { showToast } = useToast()
  const template = useDownloadClientsImportTemplate()
  const importClients = useImportClients()

  return (
    <Stack gap="md" className="csv-import">
      <div className="page-head">
        <div>
          <div className="crumb">{t('admin.clients.import.crumb')}</div>
          <Text as="h1" variant="heading-md">
            {t('admin.clients.import.title')}
          </Text>
        </div>
        <Button
          variant="ghost"
          size="sm"
          className="gap-inline-xs"
          onClick={() => void navigate('/clients')}
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
          {t('admin.clients.import.back')}
        </Button>
      </div>

      <p className="lede">{t('admin.clients.import.lede')}</p>

      <CsvImportPanel
        template={template}
        runImport={importClients}
        onDone={({ created, updated }) => {
          showToast({
            tone: 'ok',
            title: t('admin.clients.import.doneTitle'),
            description: t('admin.clients.import.doneBody', { created, updated }),
          })
          void navigate('/clients')
        }}
      />
    </Stack>
  )
}
