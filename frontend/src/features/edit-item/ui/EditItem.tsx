import type { ItemId } from '@/entities/item'
import { useTranslation } from '@/shared/i18n'
import { formatTaxRate } from '@/shared/lib/format-money'
import {
  Button,
  ErrorState,
  Field,
  FormLayout,
  FormRow,
  Input,
  LoadingState,
  Select,
  Stack,
  Text,
} from '@/shared/ui'
import { useEditItem } from '../model/use-edit-item'

const TAX_RATES = [1000, 800] as const

const toNumber = (value: string): number => (value === '' ? Number.NaN : Number(value))

export interface EditItemProps {
  itemId: ItemId
}

/** Edit-item screen: loads the item, prefills the form, saves the update. */
export function EditItem({ itemId }: EditItemProps) {
  const { t } = useTranslation()
  const state = useEditItem(itemId)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.items.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.items.edit.loadError')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  const {
    register,
    formState: { errors },
  } = state.form

  return (
    <FormLayout>
      <form onSubmit={state.onSubmit} noValidate>
        <Stack gap="md">
          <Text as="h1" variant="heading-md">
            {t('admin.items.edit.title')}
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

          {state.errorMessage !== null && (
            <Text variant="muted" role="alert">
              {state.errorMessage}
            </Text>
          )}

          <div>
            <Button type="submit" disabled={state.isPending}>
              {state.isPending ? t('admin.items.edit.submitting') : t('admin.items.edit.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
