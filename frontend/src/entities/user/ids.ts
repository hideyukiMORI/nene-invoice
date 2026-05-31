export type UserId = number & { readonly __brand: 'UserId' }

export function toUserId(value: number): UserId {
  return value as UserId
}
