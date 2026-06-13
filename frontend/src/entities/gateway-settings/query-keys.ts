export const gatewaySettingsKeys = {
  all: ['gateway-settings'] as const,
  detail: () => [...gatewaySettingsKeys.all, 'detail'] as const,
}
