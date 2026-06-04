import { useWatch } from 'react-hook-form'
import { useTranslation } from '@/shared/i18n'
import { useLineGridEnter } from '@/shared/keyboard'
import { formatTaxRate, formatYen } from '@/shared/lib/format-money'
import { computeDocumentTotals } from '@/shared/lib/tax'
import { Button, Field, InlineAlert, Input, Select, Stack, Text, Textarea } from '@/shared/ui'
import { useCreateInvoice } from '../hooks/use-create-invoice'

const TAX_RATES = [1000, 800] as const

const toNumber = (value: string): number => (value === '' ? Number.NaN : Number(value))

/** Trash icon for removing a line (final-spec icon-btn). */
const trashIcon = (
  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
    <path d="M3 4h10M6.5 4V2.8h3V4M5 4l.6 9h4.8L11 4" />
  </svg>
)

/**
 * Direct invoice creation form (final design spec, screen 09): cards for client,
 * line-item editor grid, and notes + live totals, with a right-aligned action.
 */
export function CreateInvoiceForm() {
  const { t } = useTranslation()
  const { form, lines, clients, clientsLoading, onSubmit, addLine, isPending, errorMessage } =
    useCreateInvoice()
  const {
    control,
    register,
    formState: { errors },
  } = form
  const { gridRef } = useLineGridEnter(lines.fields.length, addLine)

  // Live totals preview (backend stays authoritative; mirrors TaxCalculator).
  // Empty number inputs surface as NaN; computeDocumentTotals coerces those to 0.
  const watched = useWatch({ control, name: 'line_items' })
  const totals = computeDocumentTotals(
    watched.map((line) => ({
      quantity: line.quantity,
      unit_price_cents: line.unit_price_cents,
      tax_rate_bps: line.tax_rate_bps,
    })),
  )

  return (
    <div className="content-mid">
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Text as="h1" variant="heading-md">
            {t('admin.invoices.create.title')}
          </Text>

          {/* Client */}
          <div className="card">
            <Field
              id="client_id"
              label={t('admin.invoices.create.client')}
              error={errors.client_id ? t('admin.invoices.create.invalid') : undefined}
            >
              <Select
                id="client_id"
                disabled={clientsLoading}
                aria-invalid={errors.client_id ? true : undefined}
                {...register('client_id', { setValueAs: toNumber })}
              >
                <option value="">{t('admin.invoices.create.clientPlaceholder')}</option>
                {clients.map((client) => (
                  <option key={client.id} value={client.id}>
                    {client.name}
                  </option>
                ))}
              </Select>
            </Field>
            {!clientsLoading && clients.length === 0 && (
              <Text variant="muted">{t('admin.invoices.create.noClients')}</Text>
            )}
          </div>

          {/* Line items */}
          <div className="card">
            <div className="section-title">{t('admin.invoices.create.lineItems')}</div>
            <div className="line-grid" ref={gridRef}>
              <div className="line-head">
                <span>{t('admin.invoices.line.description')}</span>
                <span className="tr">{t('admin.invoices.line.quantity')}</span>
                <span className="tr">{t('admin.invoices.line.unitPrice')}</span>
                <span>{t('admin.invoices.line.taxRate')}</span>
                <span className="tr">{t('admin.invoices.line.lineSubtotal')}</span>
                <span />
              </div>
              {lines.fields.map((field, index) => {
                const line = watched.at(index)
                const amount = (line?.quantity || 0) * (line?.unit_price_cents || 0)
                return (
                  <div className="line-row" key={field.id}>
                    <Input
                      id={`line-${index}-description`}
                      aria-label={t('admin.invoices.line.description')}
                      aria-invalid={errors.line_items?.[index]?.description ? true : undefined}
                      {...register(`line_items.${index}.description`)}
                    />
                    <Input
                      id={`line-${index}-quantity`}
                      className="num text-right"
                      type="number"
                      min={1}
                      aria-label={t('admin.invoices.line.quantity')}
                      {...register(`line_items.${index}.quantity`, { valueAsNumber: true })}
                    />
                    <Input
                      id={`line-${index}-unit`}
                      className="num text-right"
                      type="number"
                      min={0}
                      aria-label={t('admin.invoices.line.unitPrice')}
                      {...register(`line_items.${index}.unit_price_cents`, { valueAsNumber: true })}
                    />
                    <Select
                      id={`line-${index}-tax`}
                      aria-label={t('admin.invoices.line.taxRate')}
                      {...register(`line_items.${index}.tax_rate_bps`, { setValueAs: toNumber })}
                    >
                      {TAX_RATES.map((bps) => (
                        <option key={bps} value={bps}>
                          {formatTaxRate(bps)}
                        </option>
                      ))}
                    </Select>
                    <span className="line-amt">{formatYen(amount)}</span>
                    {lines.fields.length > 1 ? (
                      <button
                        type="button"
                        className="icon-btn"
                        aria-label={t('admin.invoices.create.removeLine')}
                        onClick={() => {
                          lines.remove(index)
                        }}
                      >
                        {trashIcon}
                      </button>
                    ) : (
                      <span />
                    )}
                  </div>
                )
              })}
            </div>
            <div className="mt-stack-md">
              <Button variant="ghost" size="sm" onClick={addLine}>
                ＋ {t('admin.invoices.create.addLine')}
              </Button>
            </div>
          </div>

          {/* Notes + live totals */}
          <div className="note-totals-grid">
            <div className="card">
              <Field id="notes" label={t('admin.invoices.create.notes')}>
                <Textarea
                  id="notes"
                  rows={4}
                  placeholder={t('admin.invoices.create.notesPlaceholder')}
                  {...register('notes')}
                />
              </Field>
            </div>
            <div className="card">
              <div className="totals">
                <div className="totals-row">
                  <span className="t-label">{t('admin.invoices.detail.subtotal')}</span>
                  <span className="t-val">{formatYen(totals.subtotal_cents)}</span>
                </div>
                <div className="totals-row">
                  <span className="t-label">{t('admin.invoices.detail.tax')}</span>
                  <span className="t-val">{formatYen(totals.tax_cents)}</span>
                </div>
                <div className="totals-row grand">
                  <span className="t-label">{t('admin.invoices.detail.total')}</span>
                  <span className="t-val">{formatYen(totals.total_cents)}</span>
                </div>
              </div>
            </div>
          </div>

          {errorMessage !== null && <InlineAlert tone="error" message={errorMessage} />}

          <div className="form-actions-end">
            <Button type="submit" disabled={isPending}>
              {isPending
                ? t('admin.invoices.create.submitting')
                : t('admin.invoices.create.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </div>
  )
}
