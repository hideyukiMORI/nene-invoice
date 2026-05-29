export type ClientId = number & { readonly __brand: 'ClientId' }

export function toClientId(value: number): ClientId {
  return value as ClientId
}
