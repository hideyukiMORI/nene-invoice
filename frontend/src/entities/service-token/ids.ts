export type ServiceTokenId = number & { readonly __brand: 'ServiceTokenId' }

export function toServiceTokenId(value: number): ServiceTokenId {
  return value as ServiceTokenId
}
