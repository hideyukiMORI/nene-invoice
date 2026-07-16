import { useTranslation } from '@/shared/i18n'
import { Button, Field, FormLayout, InlineAlert, Input, Stack, Text } from '@/shared/ui'
import { useCreateOrganization } from '../model/use-create-organization'

/** Create-organization (tenant) form. On success navigates to the org list. */
export function CreateOrganizationForm() {
  const { t } = useTranslation()
  const { form, createAdmin, onSubmit, isPending, errorMessage } = useCreateOrganization()
  const {
    register,
    formState: { errors },
  } = form

  return (
    <FormLayout>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Text as="h1" variant="heading-md">
            {t('admin.organizations.create.title')}
          </Text>

          <Field
            id="name"
            label={t('admin.organizations.create.name')}
            error={errors.name ? t('admin.organizations.create.nameRequired') : undefined}
          >
            <Input
              id="name"
              type="text"
              aria-invalid={errors.name ? true : undefined}
              {...register('name')}
            />
          </Field>

          <Field
            id="slug"
            label={t('admin.organizations.create.slug')}
            hint={t('admin.organizations.create.slugHint')}
            error={errors.slug ? t('admin.organizations.create.slugRequired') : undefined}
          >
            <Input
              id="slug"
              type="text"
              aria-invalid={errors.slug ? true : undefined}
              {...register('slug')}
            />
          </Field>

          <Field
            id="plan"
            label={t('admin.organizations.create.plan')}
            hint={t('admin.organizations.create.planHint')}
          >
            <Input id="plan" type="text" {...register('plan')} />
          </Field>

          <label className="flex items-center gap-inline-xs text-body text-fg-muted">
            <input type="checkbox" {...register('createAdmin')} />
            {t('admin.organizations.create.createAdmin')}
          </label>

          {createAdmin && (
            <>
              <Field
                id="adminEmail"
                label={t('admin.organizations.create.adminEmail')}
                error={
                  errors.adminEmail ? t('admin.organizations.create.adminEmailInvalid') : undefined
                }
              >
                <Input
                  id="adminEmail"
                  type="email"
                  aria-invalid={errors.adminEmail ? true : undefined}
                  {...register('adminEmail')}
                />
              </Field>

              <Field
                id="adminPassword"
                label={t('admin.organizations.create.adminPassword')}
                error={
                  errors.adminPassword
                    ? t('admin.organizations.create.adminPasswordShort')
                    : undefined
                }
              >
                <Input
                  id="adminPassword"
                  type="password"
                  aria-invalid={errors.adminPassword ? true : undefined}
                  {...register('adminPassword')}
                />
              </Field>
            </>
          )}

          {errorMessage !== null && <InlineAlert tone="error" message={errorMessage} />}

          <div>
            <Button type="submit" disabled={isPending}>
              {isPending
                ? t('admin.organizations.create.submitting')
                : t('admin.organizations.create.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
