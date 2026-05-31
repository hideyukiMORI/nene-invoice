import { Text } from '../primitives/Text'

/**
 * Inline mutation-level error shown beneath a submit button.
 * Renders nothing when `message` is null so call-sites skip the null check.
 */
export function MutationError({ message }: { message: string | null }) {
  if (message === null) return null
  return (
    <Text variant="muted" role="alert">
      {message}
    </Text>
  )
}
