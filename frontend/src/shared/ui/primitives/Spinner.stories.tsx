/**
 * Spinner — indeterminate loading indicator.
 *
 * In:  label (accessible name), className (layout only)
 * Out: none
 *
 * Does not: manage timing, fetch, or know what is loading.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { Spinner } from './Spinner'

const meta: Meta<typeof Spinner> = {
  title: 'Primitives/Spinner',
  component: Spinner,
  args: { label: 'Loading' },
}

export default meta
type Story = StoryObj<typeof Spinner>

export const Default: Story = {}
