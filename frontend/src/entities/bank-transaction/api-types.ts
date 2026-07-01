import type { components } from '@/shared/api/schema.gen'

/** Wire shapes (snake_case) straight from the OpenAPI contract. */
export type BankTransactionDto = components['schemas']['BankTransaction']
export type BankMatchSuggestionDto = components['schemas']['BankMatchSuggestion']
export type BankMatchSuggestionListDto = components['schemas']['BankMatchSuggestionList']
export type BankImportResultDto = components['schemas']['BankImportResult']
export type BankConfirmResultDto = components['schemas']['BankConfirmResult']

/**
 * List envelope. The OpenAPI `BankTransactionList` is the generic page envelope,
 * so the typed item shape is pinned here (api-types may hand-type wire shapes).
 */
export interface BankTransactionListDto {
  items: BankTransactionDto[]
  total: number
  limit: number
  offset: number
}
