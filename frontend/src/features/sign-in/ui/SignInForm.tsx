import { Button, Field, Input, Stack, Text } from '@/shared/ui'
import { useTranslation } from '@/shared/i18n'
import { useSignIn } from '../hooks/use-sign-in'

/** Login form. On success the auth store flips and the app shell reveals itself. */
export function SignInForm() {
  const { t } = useTranslation()
  const { form, onSubmit, isPending, errorMessage } = useSignIn()
  const { errors } = form.formState

  return (
    <form onSubmit={onSubmit} noValidate>
      <Stack gap="md">
        <Text as="h1" variant="heading-md">
          {t('admin.auth.title')}
        </Text>

        <Field
          id="email"
          label={t('admin.auth.email')}
          error={errors.email ? t('admin.auth.emailInvalid') : undefined}
        >
          <Input
            id="email"
            type="email"
            autoComplete="username"
            aria-invalid={errors.email ? true : undefined}
            {...form.register('email')}
          />
        </Field>

        <Field
          id="password"
          label={t('admin.auth.password')}
          error={errors.password ? t('admin.auth.passwordRequired') : undefined}
        >
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            aria-invalid={errors.password ? true : undefined}
            {...form.register('password')}
          />
        </Field>

        {errorMessage !== null && (
          <Text variant="muted" role="alert">
            {errorMessage}
          </Text>
        )}

        <Button type="submit" disabled={isPending}>
          {t('admin.auth.submit')}
        </Button>
      </Stack>
    </form>
  )
}
