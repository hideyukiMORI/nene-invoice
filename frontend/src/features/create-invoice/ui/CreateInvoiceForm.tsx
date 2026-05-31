import { useTranslation } from '@/shared/i18n'
import { formatTaxRate } from '@/shared/lib/format-money'
import { Button, Field, Input, MutationError, Select, Stack, Text } from '@/shared/ui'
import { useCreateInvoice } from '../hooks/use-create-invoice'

const TAX_RATES = [1000, 800] as const

const toNumber = (value: string): number => (value === '' ? Number.NaN : Number(value))

/** Direct invoice creation form: client + dynamic line items. */
export function CreateInvoiceForm() {
  const { t } = useTranslation()
  const { form, lines, clients, clientsLoading, onSubmit, addLine, isPending, errorMessage } =
    useCreateInvoice()
  const {
    register,
    formState: { errors },
  } = form

  return (
    <form onSubmit={onSubmit} noValidate>
      <Stack gap="lg">
        <Text as="h1" variant="heading-md">
          {t('admin.invoices.create.title')}
        </Text>

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

        <Stack gap="md">
          <Text variant="heading-sm">{t('admin.invoices.create.lineItems')}</Text>
          {lines.fields.map((field, index) => (
            <Stack key={field.id} direction="row" gap="sm">
              <Field
                id={`line-${index}-description`}
                label={t('admin.invoices.line.description')}
                error={
                  errors.line_items?.[index]?.description
                    ? t('admin.invoices.create.invalid')
                    : undefined
                }
              >
                <Input
                  id={`line-${index}-description`}
                  {...register(`line_items.${index}.description`)}
                />
              </Field>
              <Field id={`line-${index}-quantity`} label={t('admin.invoices.line.quantity')}>
                <Input
                  id={`line-${index}-quantity`}
                  type="number"
                  min={1}
                  {...register(`line_items.${index}.quantity`, {
                    valueAsNumber: true,
                  })}
                />
              </Field>
              <Field id={`line-${index}-unit`} label={t('admin.invoices.line.unitPrice')}>
                <Input
                  id={`line-${index}-unit`}
                  type="number"
                  min={0}
                  {...register(`line_items.${index}.unit_price_cents`, {
                    valueAsNumber: true,
                  })}
                />
              </Field>
              <Field id={`line-${index}-tax`} label={t('admin.invoices.line.taxRate')}>
                <Select
                  id={`line-${index}-tax`}
                  {...register(`line_items.${index}.tax_rate_bps`, {
                    setValueAs: toNumber,
                  })}
                >
                  {TAX_RATES.map((bps) => (
                    <option key={bps} value={bps}>
                      {formatTaxRate(bps)}
                    </option>
                  ))}
                </Select>
              </Field>
              {lines.fields.length > 1 && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    lines.remove(index)
                  }}
                >
                  {t('admin.invoices.create.removeLine')}
                </Button>
              )}
            </Stack>
          ))}
          <div>
            <Button variant="ghost" size="sm" onClick={addLine}>
              {t('admin.invoices.create.addLine')}
            </Button>
          </div>
        </Stack>

        <Field id="notes" label={t('admin.invoices.create.notes')}>
          <Input id="notes" {...register('notes')} />
        </Field>

        <MutationError message={errorMessage} />

        <div>
          <Button type="submit" disabled={isPending}>
            {isPending ? t('admin.invoices.create.submitting') : t('admin.invoices.create.submit')}
          </Button>
        </div>
      </Stack>
    </form>
  )
}
