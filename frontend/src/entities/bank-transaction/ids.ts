/** Branded id — prevents passing a bare number where a BankTransactionId is required. */
export type BankTransactionId = number & { readonly __brand: 'BankTransactionId' }

export function toBankTransactionId(value: number): BankTransactionId {
  return value as BankTransactionId
}
