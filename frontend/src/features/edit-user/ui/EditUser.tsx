import type { UserId } from '@/entities/user'
import { useTranslation } from '@/shared/i18n'
import {
  Button,
  ErrorState,
  Field,
  FormLayout,
  Input,
  LoadingState,
  Select,
  Stack,
  Text,
} from '@/shared/ui'
import { useEditUser } from '../model/use-edit-user'

export interface EditUserProps {
  userId: UserId
}

/** Edit-user screen: loads the user, prefills the form, saves the update. */
export function EditUser({ userId }: EditUserProps) {
  const { t } = useTranslation()
  const state = useEditUser(userId)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.users.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.users.edit.loadError')}
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
            {t('admin.users.edit.title')}
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

          <Field id="password" label={t('admin.users.edit.newPassword')}>
            <Input id="password" type="password" {...register('password')} />
          </Field>

          <Field id="role" label={t('admin.users.create.role')}>
            <Select id="role" {...register('role')}>
              <option value="member">{t('admin.users.role.member')}</option>
              <option value="admin">{t('admin.users.role.admin')}</option>
              <option value="viewer">{t('admin.users.role.viewer')}</option>
            </Select>
          </Field>

          {state.errorMessage !== null && (
            <Text variant="muted" role="alert">
              {state.errorMessage}
            </Text>
          )}

          <div>
            <Button type="submit" disabled={state.isPending}>
              {state.isPending ? t('admin.users.edit.submitting') : t('admin.users.edit.submit')}
            </Button>
          </div>
        </Stack>
      </form>
    </FormLayout>
  )
}
