import { useTranslation } from '@/shared/i18n'
import { Button, Field, FormLayout, FormRow, InlineAlert, Input, Stack, Text } from '@/shared/ui'
import { useCreateClient } from '../hooks/use-create-client'

/** Create-client form. On success navigates to the client list. */
export function CreateClientForm() {
  const { t } = useTranslation()
  const { form, onSubmit, isPending, errorMessage } = useCreateClient()
  const {
    register,
    formState: { errors },
  } = form

  return (
    <FormLayout>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Text as="h1" variant="heading-md">
            {t('admin.clients.create.title')}
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

          {errorMessage !== null && <InlineAlert tone="error" message={errorMessage} />}

          <div>
            <Button type="submit" disabled={isPending}>
              {isPending ? t('admin.clients.create.submitting') : t('admin.clients.create.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
