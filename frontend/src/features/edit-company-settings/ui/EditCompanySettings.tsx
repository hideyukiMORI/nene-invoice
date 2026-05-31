import { useTranslation } from '@/shared/i18n'
import { Button, ErrorState, Field, Input, LoadingState, Stack, Text } from '@/shared/ui'
import { useEditCompanySettings } from '../hooks/use-edit-company-settings'

/** Company settings (自社情報) edit form — upserts on submit, stays on page. */
export function EditCompanySettings() {
  const { t } = useTranslation()
  const state = useEditCompanySettings()

  if (state.kind === 'loading') {
    return (
      <LoadingState message={t('admin.settings.loading')} />
    )
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

        <Stack direction="row" gap="sm">
          <Field id="bank_name" label={t('admin.settings.bankName')}>
            <Input id="bank_name" {...register('bank_name')} />
          </Field>
          <Field id="bank_branch" label={t('admin.settings.bankBranch')}>
            <Input id="bank_branch" {...register('bank_branch')} />
          </Field>
        </Stack>

        <Stack direction="row" gap="sm">
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
        </Stack>

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
  )
}
