import { useTranslation } from '@/shared/i18n'
import { Button, Field, Input, Select, Stack, Text } from '@/shared/ui'
import { useCreateUser } from '../hooks/use-create-user'

/** Create-user form. On success navigates to the user list. */
export function CreateUserForm() {
  const { t } = useTranslation()
  const { form, onSubmit, isPending, errorMessage } = useCreateUser()
  const {
    register,
    formState: { errors },
  } = form

  return (
    <form onSubmit={onSubmit} noValidate>
      <Stack gap="md">
        <Text as="h1" variant="heading-md">
          {t('admin.users.create.title')}
        </Text>

        <Field
          id="email"
          label={t('admin.users.create.email')}
          error={errors.email ? t('admin.users.create.emailInvalid') : undefined}
        >
          <Input
            id="email"
            type="email"
            aria-invalid={errors.email ? true : undefined}
            {...register('email')}
          />
        </Field>

        <Field
          id="password"
          label={t('admin.users.create.password')}
          error={errors.password ? t('admin.users.create.passwordRequired') : undefined}
        >
          <Input
            id="password"
            type="password"
            aria-invalid={errors.password ? true : undefined}
            {...register('password')}
          />
        </Field>

        <Field id="role" label={t('admin.users.create.role')}>
          <Select id="role" {...register('role')}>
            <option value="member">{t('admin.users.role.member')}</option>
            <option value="admin">{t('admin.users.role.admin')}</option>
            <option value="viewer">{t('admin.users.role.viewer')}</option>
          </Select>
        </Field>

        {errorMessage !== null && (
          <Text variant="muted" role="alert">
            {errorMessage}
          </Text>
        )}

        <div>
          <Button type="submit" disabled={isPending}>
            {isPending ? t('admin.users.create.submitting') : t('admin.users.create.submit')}
          </Button>
        </div>
      </Stack>
    </form>
  )
}
