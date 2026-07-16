import type { ClientId } from '@/entities/client'
import { useTranslation } from '@/shared/i18n'
import {
  Button,
  ErrorState,
  Field,
  FormLayout,
  FormRow,
  Input,
  LoadingState,
  Stack,
  Text,
} from '@/shared/ui'
import { useEditClient } from '../model/use-edit-client'

export interface EditClientProps {
  clientId: ClientId
}

/** Edit-client screen: loads the client, prefills the form, saves the update. */
export function EditClient({ clientId }: EditClientProps) {
  const { t } = useTranslation()
  const state = useEditClient(clientId)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.clients.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.clients.edit.loadError')}
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
            {t('admin.clients.edit.title')}
          </Text>

          <Field
            id="name"
            label={t('admin.clients.create.name')}
            error={errors.name ? t('admin.clients.create.nameRequired') : undefined}
          >
            <Input id="name" aria-invalid={errors.name ? true : undefined} {...register('name')} />
          </Field>

          <Field id="name_kana" label={t('admin.clients.create.nameKana')}>
            <Input
              id="name_kana"
              placeholder={t('admin.clients.create.nameKanaPlaceholder')}
              {...register('name_kana')}
            />
          </Field>

          <FormRow>
            <Field id="contact_name" label={t('admin.clients.create.contact')}>
              <Input id="contact_name" {...register('contact_name')} />
            </Field>

            <Field id="email" label={t('admin.clients.create.email')}>
              <Input id="email" type="email" {...register('email')} />
            </Field>
          </FormRow>

          <Field id="billing_address" label={t('admin.clients.create.billingAddress')}>
            <Input id="billing_address" {...register('billing_address')} />
          </Field>

          <Field id="registration_number" label={t('admin.clients.create.registration')}>
            <Input id="registration_number" {...register('registration_number')} />
          </Field>

          {state.errorMessage !== null && (
            <Text variant="muted" role="alert">
              {state.errorMessage}
            </Text>
          )}

          <div>
            <Button type="submit" disabled={state.isPending}>
              {state.isPending
                ? t('admin.clients.edit.submitting')
                : t('admin.clients.edit.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
