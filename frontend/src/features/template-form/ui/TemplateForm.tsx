import type { UseFieldArrayReturn, UseFormReturn } from 'react-hook-form'
import type { TemplateId } from '@/entities/template'
import { useTranslation } from '@/shared/i18n'
import { useLineGridEnter } from '@/shared/keyboard'
import { formatTaxRate } from '@/shared/lib/format-money'
import {
  Button,
  ErrorState,
  Field,
  FormLayout,
  InlineAlert,
  Input,
  LoadingState,
  Select,
  Stack,
  Text,
  Textarea,
} from '@/shared/ui'
import { useTemplateForm, type TemplateFormValues } from '../model/use-template-form'

const TAX_RATES = [1000, 800] as const

const toNumber = (value: string): number => (value === '' ? Number.NaN : Number(value))

const trashIcon = (
  <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
    <path d="M3 4h10M6.5 4V2.8h3V4M5 4l.6 9h4.8L11 4" />
  </svg>
)

export interface TemplateFormProps {
  templateId?: TemplateId
}

/** Create / edit a named template: name + notes + a line-preset editor grid. */
export function TemplateForm({ templateId }: TemplateFormProps) {
  const { t } = useTranslation()
  const state = useTemplateForm(templateId)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.templates.loading')} />
  }
  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.templates.form.loadError')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  const { form, lines, addLine, onSubmit, isPending, errorMessage, isEdit } = state
  const {
    register,
    formState: { errors },
  } = form

  return (
    <FormLayout>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Text as="h1" variant="heading-md">
            {isEdit ? t('admin.templates.form.editTitle') : t('admin.templates.form.createTitle')}
          </Text>

          <Field
            id="name"
            label={t('admin.templates.form.name')}
            error={errors.name ? t('admin.templates.form.nameRequired') : undefined}
          >
            <Input id="name" aria-invalid={errors.name ? true : undefined} {...register('name')} />
          </Field>

          <Field id="notes" label={t('admin.templates.form.notes')}>
            <Textarea id="notes" rows={3} {...register('notes')} />
          </Field>

          <div className="card">
            <div className="section-title">{t('admin.templates.form.lineItems')}</div>
            <LineGrid lines={lines} register={register} addLine={addLine} />
          </div>

          {errorMessage !== null && <InlineAlert tone="error" message={errorMessage} />}

          <div>
            <Button type="submit" disabled={isPending}>
              {isPending ? t('admin.templates.form.submitting') : t('admin.templates.form.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}

interface LineGridProps {
  lines: UseFieldArrayReturn<TemplateFormValues, 'line_items'>
  register: UseFormReturn<TemplateFormValues>['register']
  addLine: () => void
}

/** Line-preset editor grid (mirrors the document line grid; no totals). */
function LineGrid({ lines, register, addLine }: LineGridProps) {
  const { t } = useTranslation()
  const { gridRef } = useLineGridEnter(lines.fields.length, addLine)

  return (
    <>
      <div className="line-grid line-grid-template" ref={gridRef}>
        <div className="line-head">
          <span>{t('admin.invoices.line.description')}</span>
          <span className="tr">{t('admin.invoices.line.quantity')}</span>
          <span className="tr">{t('admin.invoices.line.unitPrice')}</span>
          <span>{t('admin.invoices.line.taxRate')}</span>
          <span />
        </div>
        {lines.fields.map((field, index) => (
          <div className="line-row" key={field.id}>
            <Input
              id={`line-${index}-description`}
              aria-label={t('admin.invoices.line.description')}
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
            {lines.fields.length > 1 ? (
              <button
                type="button"
                className="icon-btn"
                aria-label={t('admin.templates.form.removeLine')}
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
        ))}
      </div>
      <div className="mt-stack-md">
        <Button variant="ghost" size="sm" onClick={addLine}>
          ＋ {t('admin.templates.form.addLine')}
        </Button>
      </div>
    </>
  )
}
