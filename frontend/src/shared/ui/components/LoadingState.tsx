import { Spinner } from '../primitives/Spinner'
import { Stack } from '../primitives/Stack'
import { Text } from '../primitives/Text'

/** Inline loading indicator — matches the loading branch used in every feature. */
export function LoadingState({ message }: { message: string }) {
  return (
    <Stack direction="row" gap="sm">
      <Spinner label={message} />
      <Text variant="muted">{message}</Text>
    </Stack>
  )
}
