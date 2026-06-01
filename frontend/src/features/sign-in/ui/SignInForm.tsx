import { Button, Field, Input, MutationError, Stack } from '@/shared/ui'
import { useTranslation } from '@/shared/i18n'
import { useSignIn } from '../hooks/use-sign-in'

/** Login form (right panel of the split-screen). On success the auth store
 *  flips and the app shell reveals itself. */
export function SignInForm() {
  const { t } = useTranslation()
  const { form, onSubmit, isPending, errorMessage } = useSignIn()
  const { errors } = form.formState

  return (
    <form onSubmit={onSubmit} noValidate>
      <h1 className="af-head">{t('admin.auth.title')}</h1>
      <p className="af-headsub">{t('admin.auth.headSub')}</p>

      <Stack gap="md">
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

        <div className="auth-optrow">
          <label className="auth-check">
            <input type="checkbox" />
            {t('admin.auth.remember')}
          </label>
          <button type="button" className="btn-link">
            {t('admin.auth.forgotPasswordLink')}
          </button>
        </div>

        <Button type="submit" disabled={isPending} className="w-full py-stack-sm">
          {t('admin.auth.submit')}
        </Button>

        <p className="auth-secnote">
          <svg
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            aria-hidden="true"
          >
            <rect x="3" y="7" width="10" height="7" rx="1" />
            <path d="M5 7V5a3 3 0 0 1 6 0v2" />
          </svg>
          {t('admin.auth.secNote')}
        </p>
      </Stack>
    </form>
  )
}
