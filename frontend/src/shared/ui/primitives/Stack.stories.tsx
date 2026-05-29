/**
 * Stack — flexbox layout primitive (token-based gap).
 *
 * In:  direction, gap, children
 * Out: none
 *
 * Does not: own colors, spacing literals, or domain logic.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { Stack } from './Stack'
import { Text } from './Text'

const meta: Meta<typeof Stack> = {
  title: 'Primitives/Stack',
  component: Stack,
}

export default meta
type Story = StoryObj<typeof Stack>

export const Column: Story = {
  args: {
    direction: 'column',
    gap: 'md',
    children: (
      <>
        <Text>One</Text>
        <Text>Two</Text>
      </>
    ),
  },
}

export const Row: Story = {
  args: {
    direction: 'row',
    gap: 'sm',
    children: (
      <>
        <Text>Left</Text>
        <Text>Right</Text>
      </>
    ),
  },
}
