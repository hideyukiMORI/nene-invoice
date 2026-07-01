export type OrganizationId = number & { readonly __brand: 'OrganizationId' }

export function toOrganizationId(value: number): OrganizationId {
  return value as OrganizationId
}
