import { useTranslation } from '@/shared/i18n'
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
import { useEditCompanySettings } from '../hooks/use-edit-company-settings'

/** 1–31 for the closing-day / pay-day selects (末日 is the empty option). */
const DAY_OPTIONS = Array.from({ length: 31 }, (_, i) => i + 1)

/** Company settings (自社情報) edit form — upserts on submit, stays on page. */
export function EditCompanySettings() {
  const { t } = useTranslation()
  const state = useEditCompanySettings()

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.settings.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.settings.loadError')}
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
            {t('admin.settings.title')}
          </Text>

          <Field
            id="legal_name"
            label={t('admin.settings.legalName')}
            error={errors.legal_name ? t('admin.settings.legalNameRequired') : undefined}
          >
            <Input
              id="legal_name"
              aria-invalid={errors.legal_name ? true : undefined}
              {...register('legal_name')}
            />
          </Field>

          <Field id="address" label={t('admin.settings.address')}>
            <Input id="address" {...register('address')} />
          </Field>

          <Field id="phone" label={t('admin.settings.phone')}>
            <Input id="phone" type="tel" {...register('phone')} />
          </Field>

          <Field id="email" label={t('admin.settings.email')}>
            <Input id="email" type="email" {...register('email')} />
          </Field>

          <Field id="registration_number" label={t('admin.settings.registrationNumber')}>
            <Input
              id="registration_number"
              placeholder="T1234567890123"
              {...register('registration_number')}
            />
            <Text variant="muted" className="text-caption">
              {t('admin.settings.registrationNumberHint')}
            </Text>
          </Field>

          <Text variant="heading-sm">{t('admin.settings.bankSection')}</Text>

          <FormRow>
            <Field id="bank_name" label={t('admin.settings.bankName')}>
              <Input id="bank_name" {...register('bank_name')} />
            </Field>
            <Field id="bank_branch" label={t('admin.settings.bankBranch')}>
              <Input id="bank_branch" {...register('bank_branch')} />
            </Field>
          </FormRow>

          <FormRow>
            <Field id="account_type" label={t('admin.settings.accountType')}>
              <Input
                id="account_type"
                placeholder={t('admin.settings.accountTypePlaceholder')}
                {...register('account_type')}
              />
            </Field>
            <Field id="account_number" label={t('admin.settings.accountNumber')}>
              <Input id="account_number" {...register('account_number')} />
            </Field>
          </FormRow>

          <Text variant="heading-sm">{t('admin.settings.billingSection')}</Text>

          <Field id="default_quote_validity_days" label={t('admin.settings.quoteValidityDays')}>
            <Input
              id="default_quote_validity_days"
              type="number"
              min={1}
              max={3650}
              inputMode="numeric"
              placeholder={t('admin.settings.quoteValidityPlaceholder')}
              {...register('default_quote_validity_days')}
            />
            <Text variant="muted" className="text-caption">
              {t('admin.settings.quoteValidityHint')}
            </Text>
          </Field>

          <Text variant="muted" className="text-caption">
            {t('admin.settings.paymentTermsHint')}
          </Text>
          <FormRow>
            <Field id="default_payment_closing_day" label={t('admin.settings.closingDay')}>
              <Select id="default_payment_closing_day" {...register('default_payment_closing_day')}>
                <option value="">{t('admin.settings.monthEnd')}</option>
                {DAY_OPTIONS.map((d) => (
                  <option key={d} value={d}>
                    {d}
                  </option>
                ))}
              </Select>
            </Field>
            <Field id="default_payment_month_offset" label={t('admin.settings.monthOffset')}>
              <Select
                id="default_payment_month_offset"
                {...register('default_payment_month_offset')}
              >
                <option value="">{t('admin.settings.paymentTermsOff')}</option>
                <option value="0">{t('admin.settings.offsetCurrent')}</option>
                <option value="1">{t('admin.settings.offsetNext')}</option>
                <option value="2">{t('admin.settings.offsetNext2')}</option>
              </Select>
            </Field>
            <Field id="default_payment_pay_day" label={t('admin.settings.payDay')}>
              <Select id="default_payment_pay_day" {...register('default_payment_pay_day')}>
                <option value="">{t('admin.settings.monthEnd')}</option>
                {DAY_OPTIONS.map((d) => (
                  <option key={d} value={d}>
                    {d}
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

          {state.savedMessage !== null && (
            <Text variant="muted" role="status">
              {state.savedMessage}
            </Text>
          )}

          <div>
            <Button type="submit" disabled={state.isPending}>
              {state.isPending ? t('admin.settings.submitting') : t('admin.settings.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
