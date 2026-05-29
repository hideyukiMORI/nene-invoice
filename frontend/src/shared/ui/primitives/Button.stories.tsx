/**
 * Button — primary action control.
 *
 * In:  variant, size, disabled, type, children (label)
 * Out: onClick(event)
 *
 * Does not: fetch data, know entity ids, or read router/query cache.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { Button } from './Button'

const meta: Meta<typeof Button> = {
  title: 'Primitives/Button',
  component: Button,
  args: { children: 'Save' },
}

export default meta
type Story = StoryObj<typeof Button>

export const Primary: Story = { args: { variant: 'primary' } }
export const Danger: Story = { args: { variant: 'danger', children: 'Delete' } }
export const Ghost: Story = { args: { variant: 'ghost', children: 'Cancel' } }
export const Disabled: Story = { args: { disabled: true } }
