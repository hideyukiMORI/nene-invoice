import type { LineItem } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatTaxRate, formatYen } from '@/shared/lib/format-money'
import { Stack } from '../primitives/Stack'
import { Text } from '../primitives/Text'

/** Line-item table shared by invoice and quote detail screens. */
export function LineItemsTable({ items }: { items: LineItem[] }) {
  const { t } = useTranslation()
  return (
    <table className="w-full border-collapse text-body">
      <thead>
        <tr className="border-b border-border text-left">
          <th className="py-stack-sm pr-inline-md font-medium">
            {t('admin.invoices.line.description')}
          </th>
          <th className="py-stack-sm pr-inline-md text-right font-medium">
            {t('admin.invoices.line.quantity')}
          </th>
          <th className="py-stack-sm pr-inline-md text-right font-medium">
            {t('admin.invoices.line.unitPrice')}
          </th>
          <th className="py-stack-sm pr-inline-md text-right font-medium">
            {t('admin.invoices.line.taxRate')}
          </th>
          <th className="py-stack-sm text-right font-medium">
            {t('admin.invoices.line.lineSubtotal')}
          </th>
        </tr>
      </thead>
      <tbody>
        {items.map((line, index) => (
          <tr key={index} className="border-b border-border">
            <td className="py-stack-sm pr-inline-md">{line.description}</td>
            <td className="py-stack-sm pr-inline-md text-right">{line.quantity}</td>
            <td className="py-stack-sm pr-inline-md text-right">
              {formatYen(line.unit_price_cents)}
            </td>
            <td className="py-stack-sm pr-inline-md text-right">
              {formatTaxRate(line.tax_rate_bps)}
            </td>
            <td className="py-stack-sm text-right">{formatYen(line.line_subtotal_cents)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

/** Label / value row for the totals summary at the bottom of a document. */
export function TotalRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between">
      <Text variant="muted">{label}</Text>
      <Text>{value}</Text>
    </div>
  )
}
