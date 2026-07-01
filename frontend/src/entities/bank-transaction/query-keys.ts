import type { BankTransactionStatus } from './enum'

export interface BankTransactionListParams {
  /** null = any status. */
  status: BankTransactionStatus | null
  limit: number
  offset: number
}

/** Hierarchical, typed query keys — features never write key strings. */
export const bankTransactionKeys = {
  all: ['bank-transactions'] as const,
  lists: () => [...bankTransactionKeys.all, 'list'] as const,
  list: (params: BankTransactionListParams) => [...bankTransactionKeys.lists(), params] as const,
  suggestions: (id: number) => [...bankTransactionKeys.all, 'suggestions', id] as const,
}
