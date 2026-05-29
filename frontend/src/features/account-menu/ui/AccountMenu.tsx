import { useTranslation } from '@/shared/i18n'
import { Button, Stack, Text } from '@/shared/ui'
import { useAccountMenu } from '../hooks/use-account-menu'

/** Header account summary + sign-out. */
export function AccountMenu() {
  const { t } = useTranslation()
  const { email, onSignOut } = useAccountMenu()

  return (
    <Stack direction="row" gap="md">
      {email !== null && <Text variant="muted">{t('admin.account.signedInAs', { email })}</Text>}
      <Button variant="ghost" size="sm" onClick={onSignOut}>
        {t('common.actions.signOut')}
      </Button>
    </Stack>
  )
}
