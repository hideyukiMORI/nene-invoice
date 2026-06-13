import { EditCompanySettings } from '@/features/edit-company-settings'
import { GatewaySettings } from '@/features/gateway-settings'
import { ManageCompanySeal } from '@/features/manage-company-seal'
import { FormLayout, Stack } from '@/shared/ui'

export function CompanySettingsPage() {
  return (
    <Stack gap="lg">
      <EditCompanySettings />
      <FormLayout>
        <ManageCompanySeal />
      </FormLayout>
      <FormLayout>
        <GatewaySettings />
      </FormLayout>
    </Stack>
  )
}
