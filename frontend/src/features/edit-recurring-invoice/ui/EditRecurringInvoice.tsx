import { Controller, useWatch } from 'react-hook-form'
import { RECURRING_FREQUENCIES, type RecurringInvoiceId } from '@/entities/recurring-invoice'
import { useTranslation } from '@/shared/i18n'
import { useLineGridEnter } from '@/shared/keyboard'
import { formatTaxRate, formatYen } from '@/shared/lib/format-money'
import { computeDocumentTotals } from '@/shared/lib/tax'
import {
  Button,
  ClientCombobox,
  DatePicker,
  ErrorState,
  Field,
  FormRow,
  InlineAlert,
  Input,
  type LineSuggestion,
  LineItemSuggestInput,
  LoadingState,
  Select,
  Stack,
  Text,
  Textarea,
} from '@/shared/ui'
import {
  useEditRecurringInvoice,
  type EditRecurringInvoiceReady,
} from '../hooks/use-edit-recurring-invoice'

const TAX_RATES = [1000, 800] as const

const toNumber = (value: string): number => (value === '' ? Number.NaN : Number(value))

/** Trash icon for removing a line (final-spec icon-btn). */
const trashIcon = (
  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
    <path d="M3 4h10M6.5 4V2.8h3V4M5 4l.6 9h4.8L11 4" />
  </svg>
)

export interface EditRecurringInvoiceProps {
  recurringInvoiceId: RecurringInvoiceId
}

/** Edit-recurring-invoice screen: loads the schedule, prefills the form, saves it. */
export function EditRecurringInvoice({ recurringInvoiceId }: EditRecurringInvoiceProps) {
  const { t } = useTranslation()
  const state = useEditRecurringInvoice(recurringInvoiceId)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.recurring.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.recurring.edit.loadError')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  return <EditRecurringInvoiceForm state={state} />
}

/** Inner form, rendered once the schedule is loaded (so hooks run unconditionally). */
function EditRecurringInvoiceForm({ state }: { state: EditRecurringInvoiceReady }) {
  const { t } = useTranslation()
  const {
    form,
    lines,
    clients,
    clientsLoading,
    createClient,
    lineSuggestions,
    onClientQueryChange,
    onSubmit,
    addLine,
    isPending,
    errorMessage,
  } = state
  const {
    control,
    register,
    setValue,
    formState: { errors },
  } = form
  const { gridRef } = useLineGridEnter(lines.fields.length, addLine)

  const applySuggestion = (index: number, s: LineSuggestion): void => {
    setValue(`line_items.${index}.description`, s.description, { shouldValidate: true })
    setValue(`line_items.${index}.unit_price_cents`, s.unit_price_cents, { shouldValidate: true })
    if (s.tax_rate_bps === 800 || s.tax_rate_bps === 1000) {
      setValue(`line_items.${index}.tax_rate_bps`, s.tax_rate_bps, { shouldValidate: true })
    }
  }

  const suggestionMeta = (s: LineSuggestion): string => {
    const parts = [formatYen(s.unit_price_cents), formatTaxRate(s.tax_rate_bps)]
    if (s.source === 'master') parts.push(t('admin.lineItemSuggest.master'))
    if (s.usage_count > 0) parts.push(t('admin.lineItemSuggest.usage', { count: s.usage_count }))
    return parts.join(' · ')
  }

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
            {t('admin.recurring.edit.title')}
          </Text>

          {/* Schedule header */}
          <div className="card">
            <Stack gap="md">
              <FormRow>
                <Field
                  id="client_id"
                  label={t('admin.recurring.create.client')}
                  error={errors.client_id ? t('admin.recurring.create.invalid') : undefined}
                >
                  <Controller
                    control={control}
                    name="client_id"
                    render={({ field }) => (
                      <ClientCombobox
                        id="client_id"
                        clients={clients}
                        value={field.value}
                        onChange={field.onChange}
                        onCreate={createClient}
                        onQueryChange={onClientQueryChange}
                        loading={clientsLoading}
                        invalid={errors.client_id !== undefined}
                        placeholder={t('admin.clientPicker.placeholder')}
                        createLabel={(name) => t('admin.clientPicker.create', { name })}
                        createKanaPlaceholder={t('admin.clientPicker.kanaPlaceholder')}
                        createConfirmLabel={t('admin.clientPicker.createConfirm')}
                      />
                    )}
                  />
                </Field>
                <Field
                  id="name"
                  label={t('admin.recurring.create.name')}
                  error={errors.name ? t('admin.recurring.create.nameRequired') : undefined}
                >
                  <Input
                    id="name"
                    aria-invalid={errors.name ? true : undefined}
                    placeholder={t('admin.recurring.create.namePlaceholder')}
                    {...register('name')}
                  />
                </Field>
              </FormRow>
              <FormRow>
                <Field id="frequency" label={t('admin.recurring.create.frequency')}>
                  <Select id="frequency" {...register('frequency')}>
                    {RECURRING_FREQUENCIES.map((freq) => (
                      <option key={freq} value={freq}>
                        {t(`admin.recurring.frequency.${freq}`)}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field
                  id="next_run_on"
                  label={t('admin.recurring.edit.nextRunOn')}
                  error={
                    errors.next_run_on ? t('admin.recurring.edit.nextRunOnRequired') : undefined
                  }
                >
                  <Controller
                    control={control}
                    name="next_run_on"
                    render={({ field }) => (
                      <DatePicker id="next_run_on" value={field.value} onChange={field.onChange} />
                    )}
                  />
                </Field>
              </FormRow>
              <label className="flex items-center gap-inline-xs text-body text-fg-muted">
                <input type="checkbox" {...register('is_active')} />
                {t('admin.recurring.create.isActive')}
              </label>
            </Stack>
          </div>

          {/* Line items */}
          <div className="card">
            <div className="section-title">{t('admin.recurring.create.lineItems')}</div>
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
                    <Controller
                      control={control}
                      name={`line_items.${index}.description`}
                      render={({ field }) => (
                        <LineItemSuggestInput
                          id={`line-${index}-description`}
                          aria-label={t('admin.invoices.line.description')}
                          invalid={errors.line_items?.[index]?.description !== undefined}
                          value={field.value}
                          onChange={field.onChange}
                          suggestions={lineSuggestions}
                          onPick={(s) => {
                            applySuggestion(index, s)
                          }}
                          renderMeta={suggestionMeta}
                        />
                      )}
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
                        aria-label={t('admin.recurring.create.removeLine')}
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
                ＋ {t('admin.recurring.create.addLine')}
              </Button>
            </div>
          </div>

          {/* Notes + live totals */}
          <div className="note-totals-grid">
            <div className="card">
              <Field id="notes" label={t('admin.recurring.create.notes')}>
                <Textarea
                  id="notes"
                  rows={4}
                  placeholder={t('admin.recurring.create.notesPlaceholder')}
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
              {isPending ? t('admin.recurring.edit.submitting') : t('admin.recurring.edit.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </div>
  )
}
