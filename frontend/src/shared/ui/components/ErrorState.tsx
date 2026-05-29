import { Button } from '../primitives/Button'
import { Stack } from '../primitives/Stack'
import { Text } from '../primitives/Text'

export interface ErrorStateProps {
  message: string
  retryLabel: string
  onRetry: () => void
}

/** Safe error panel with a retry action. Never surfaces raw error internals. */
export function ErrorState({ message, retryLabel, onRetry }: ErrorStateProps) {
  return (
    <div
      role="alert"
      className="rounded-md border border-border bg-surface-raised px-inline-lg py-stack-lg"
    >
      <Stack gap="sm">
        <Text>{message}</Text>
        <div>
          <Button variant="ghost" size="sm" onClick={onRetry}>
            {retryLabel}
          </Button>
        </div>
      </Stack>
    </div>
  )
}
