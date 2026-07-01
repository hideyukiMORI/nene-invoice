import { useTranslation } from '@/shared/i18n'
import { Stack, Text } from '@/shared/ui'
import { BankImportPanel } from './BankImportPanel'
import { BankWorkbench } from './BankWorkbench'

/**
 * Bank reconciliation workbench (自動消込 ⑧, epic #505): import a bank CSV, then
 * review staged lines and confirm each deposit against an invoice. Advice only —
 * a payment is recorded only when the user confirms a suggested match.
 */
export function ReconcileBank() {
  const { t } = useTranslation()

  return (
    <Stack gap="lg">
      <div className="page-head">
        <div>
          <div className="crumb">{t('admin.bankReconciliation.crumb')}</div>
          <Text as="h1" variant="heading-md">
            {t('admin.bankReconciliation.title')}
          </Text>
        </div>
      </div>

      <p className="lede">{t('admin.bankReconciliation.lede')}</p>

      <BankImportPanel />
      <BankWorkbench />
    </Stack>
  )
}
