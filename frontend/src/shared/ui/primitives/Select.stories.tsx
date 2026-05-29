/**
 * Select — single-choice dropdown primitive.
 *
 * In:  value, disabled, children (<option>s), aria-* (and a forwarded ref)
 * Out: onChange(event)
 *
 * Does not: own labels, options data, validation, or form state.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { Select } from './Select'

const meta: Meta<typeof Select> = {
  title: 'Primitives/Select',
  component: Select,
  render: (args) => (
    <Select {...args}>
      <option value="">選択してください</option>
      <option value="1">取引先 A</option>
      <option value="2">取引先 B</option>
    </Select>
  ),
}

export default meta
type Story = StoryObj<typeof Select>

export const Default: Story = {}
export const Disabled: Story = { args: { disabled: true } }
