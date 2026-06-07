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
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.clients.import.title')}
        </Text>
        <Button variant="ghost" size="sm" onClick={() => void navigate('/clients')}>
          {t('admin.clients.import.back')}
        </Button>
      </div>

      <Text variant="muted">{t('admin.clients.import.lede')}</Text>

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
