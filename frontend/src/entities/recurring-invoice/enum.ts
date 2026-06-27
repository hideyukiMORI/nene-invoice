export const RECURRING_FREQUENCIES = ['monthly', 'quarterly'] as const

export type RecurringFrequency = (typeof RECURRING_FREQUENCIES)[number]
