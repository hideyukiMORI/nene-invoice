import { EditCompanySettings } from '@/features/edit-company-settings'
import { GatewaySettings } from '@/features/gateway-settings'
import { FormLayout, Stack } from '@/shared/ui'

export function CompanySettingsPage() {
  return (
    <Stack gap="lg">
      <EditCompanySettings />
      <FormLayout>
        <GatewaySettings />
      </FormLayout>
    </Stack>
  )
}
