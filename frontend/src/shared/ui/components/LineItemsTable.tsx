import { useTranslation } from '@/shared/i18n'
import { formatTaxRate, formatYen } from '@/shared/lib/format-money'

/**
 * Minimal row shape a document line must provide. Kept local so the shared UI
 * layer stays independent of the entities layer (FSD import boundary).
 */
export interface LineItemRow {
  description: string
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
  line_subtotal_cents: number
}

/** Line-item table shared by invoice and quote detail screens. */
export function LineItemsTable({ items }: { items: LineItemRow[] }) {
  const { t } = useTranslation()
  return (
    <div className="table-scroll">
      <table className="data-table">
        <thead>
          <tr>
            <th>{t('admin.invoices.line.description')}</th>
            <th className="tr">{t('admin.invoices.line.quantity')}</th>
            <th className="tr">{t('admin.invoices.line.unitPrice')}</th>
            <th className="tr">{t('admin.invoices.line.taxRate')}</th>
            <th className="tr">{t('admin.invoices.line.lineSubtotal')}</th>
          </tr>
        </thead>
        <tbody>
          {items.map((line, index) => (
            <tr key={index}>
              <td data-label={t('admin.invoices.line.description')}>{line.description}</td>
              <td className="tr num" data-label={t('admin.invoices.line.quantity')}>
                {line.quantity}
              </td>
              <td className="tr num" data-label={t('admin.invoices.line.unitPrice')}>
                {formatYen(line.unit_price_cents)}
              </td>
              <td className="tr num" data-label={t('admin.invoices.line.taxRate')}>
                {formatTaxRate(line.tax_rate_bps)}
              </td>
              <td className="tr num" data-label={t('admin.invoices.line.lineSubtotal')}>
                {formatYen(line.line_subtotal_cents)}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
