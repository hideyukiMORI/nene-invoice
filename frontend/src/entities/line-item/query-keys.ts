export const lineItemKeys = {
  all: ['line-items'] as const,
  suggestions: () => [...lineItemKeys.all, 'suggestions'] as const,
}
