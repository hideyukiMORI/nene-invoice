import { Text } from '../primitives/Text'

export interface EmptyStateProps {
  message: string
}

/** Intentional empty-result state — never a blank page. */
export function EmptyState({ message }: EmptyStateProps) {
  return (
    <div className="rounded-md border border-border bg-surface-raised px-inline-lg py-stack-lg text-center">
      <Text variant="muted">{message}</Text>
    </div>
  )
}
