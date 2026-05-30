declare const __quoteId: unique symbol
export type QuoteId = number & { readonly [__quoteId]: true }
export const toQuoteId = (n: number): QuoteId => n as QuoteId
