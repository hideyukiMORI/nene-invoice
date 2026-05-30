export const companySettingsKeys = {
  all: ['company-settings'] as const,
  detail: () => [...companySettingsKeys.all, 'detail'] as const,
}
