/**
 * Input — single-line text field primitive.
 *
 * In:  type, value, placeholder, disabled, aria-* (and a forwarded ref)
 * Out: onChange(event), onBlur(event)
 *
 * Does not: own labels, validation, or form state — features compose those.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { Input } from './Input'

const meta: Meta<typeof Input> = {
  title: 'Primitives/Input',
  component: Input,
  args: { placeholder: 'name@example.com' },
}

export default meta
type Story = StoryObj<typeof Input>

export const Default: Story = {}
export const Disabled: Story = { args: { disabled: true } }
