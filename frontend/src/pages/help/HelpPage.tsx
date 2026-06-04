import { useTranslation } from '@/shared/i18n'
import { EmptyState, Stack } from '@/shared/ui'

/**
 * Help landing page (design 03 — stub). FAQ / operation guides connect here
 * later. Distinct from the `?` shortcut cheat-sheet (keys), which is separate.
 */
export function HelpPage() {
  const { t } = useTranslation()

  return (
    <Stack gap="md">
      <div className="page-head">
        <div>
          <h1 className="page-title">{t('admin.help.title')}</h1>
          <p className="page-sub">{t('admin.help.subtitle')}</p>
        </div>
      </div>
      <div className="panel">
        <div className="panel-body">
          <EmptyState message={t('admin.help.comingSoon')} />
        </div>
      </div>
    </Stack>
  )
}
