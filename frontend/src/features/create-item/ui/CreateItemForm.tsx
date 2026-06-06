import { useTranslation } from '@/shared/i18n'
import { formatTaxRate } from '@/shared/lib/format-money'
import {
  Button,
  Field,
  FormLayout,
  FormRow,
  InlineAlert,
  Input,
  Select,
  Stack,
  Text,
} from '@/shared/ui'
import { useCreateItem } from '../hooks/use-create-item'

const TAX_RATES = [1000, 800] as const

const toNumber = (value: string): number => (value === '' ? Number.NaN : Number(value))

/** Create-item form. On success navigates to the item list. */
export function CreateItemForm() {
  const { t } = useTranslation()
  const { form, onSubmit, isPending, errorMessage } = useCreateItem()
  const {
    register,
    formState: { errors },
  } = form

  return (
    <FormLayout>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Text as="h1" variant="heading-md">
            {t('admin.items.create.title')}
          </Text>

          <Field
            id="description"
            label={t('admin.items.create.description')}
            error={errors.description ? t('admin.items.create.descriptionRequired') : undefined}
          >
            <Input
              id="description"
              aria-invalid={errors.description ? true : undefined}
              {...register('description')}
            />
          </Field>

          <FormRow>
            <Field
              id="default_unit_price_cents"
              label={t('admin.items.create.unitPrice')}
              error={
                errors.default_unit_price_cents
                  ? t('admin.items.create.unitPriceInvalid')
                  : undefined
              }
            >
              <Input
                id="default_unit_price_cents"
                className="num text-right"
                type="number"
                min={0}
                aria-invalid={errors.default_unit_price_cents ? true : undefined}
                {...register('default_unit_price_cents', { valueAsNumber: true })}
              />
            </Field>

            <Field id="default_tax_rate_bps" label={t('admin.items.create.taxRate')}>
              <Select
                id="default_tax_rate_bps"
                {...register('default_tax_rate_bps', { setValueAs: toNumber })}
              >
                {TAX_RATES.map((bps) => (
                  <option key={bps} value={bps}>
                    {formatTaxRate(bps)}
                  </option>
                ))}
              </Select>
            </Field>
          </FormRow>

          {errorMessage !== null && <InlineAlert tone="error" message={errorMessage} />}

          <div>
            <Button type="submit" disabled={isPending}>
              {isPending ? t('admin.items.create.submitting') : t('admin.items.create.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
