export const companySealKeys = {
  all: ['company-seal'] as const,
  detail: () => [...companySealKeys.all, 'detail'] as const,
}
