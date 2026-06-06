export type ItemId = number & { readonly __brand: 'ItemId' }

export function toItemId(value: number): ItemId {
  return value as ItemId
}
