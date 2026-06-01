import { Button, Field, Input, MutationError, Stack } from '@/shared/ui'
import { useTranslation } from '@/shared/i18n'
import { useSignIn } from '../hooks/use-sign-in'

/** Overlapping-N monogram (案C 採用ロゴ). */
function MonogramMark() {
  return (
    <span className="mono-mark" aria-hidden="true">
      <svg viewBox="0 0 42 42">
        <text
          x="-2"
          y="31"
          fontFamily="sans-serif"
          fontWeight="800"
          fontSize="32"
          fill="currentColor"
          opacity="0.32"
        >
          N
        </text>
        <text
          x="11"
          y="31"
          fontFamily="sans-serif"
          fontWeight="800"
          fontSize="32"
          fill="currentColor"
        >
          N
        </text>
      </svg>
    </span>
  )
}

/** Login form. On success the auth store flips and the app shell reveals itself. */
export function SignInForm() {
  const { t } = useTranslation()
  const { form, onSubmit, isPending, errorMessage } = useSignIn()
  const { errors } = form.formState

  return (
    <form onSubmit={onSubmit} noValidate>
      <h1 className="sr-only">{t('admin.auth.title')}</h1>
      <Stack gap="md">
        <Stack gap="sm" className="items-center text-center">
          <div className="auth-brand">
            <MonogramMark />
            <span className="auth-name">NeNe Invoice</span>
          </div>
          <p className="text-caption text-fg-muted">{t('admin.auth.subtitle')}</p>
        </Stack>

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

        <MutationError message={errorMessage} />

        <Button type="submit" disabled={isPending} className="w-full py-stack-sm">
          {t('admin.auth.submit')}
        </Button>

        <p className="text-center text-caption text-fg-muted">
          {t('admin.auth.forgotPasswordPrompt')}{' '}
          <button type="button" className="text-accent hover:underline">
            {t('admin.auth.forgotPasswordLink')}
          </button>
        </p>
      </Stack>
    </form>
  )
}
