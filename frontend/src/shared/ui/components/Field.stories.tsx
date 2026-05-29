/**
 * Field — label + control + optional error.
 *
 * In:  id, label, error, children (the control)
 * Out: none (control emits its own events)
 *
 * Does not: own form state or validation logic.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { Input } from '../primitives/Input'
import { Field } from './Field'

const meta: Meta<typeof Field> = {
  title: 'Components/Field',
  component: Field,
  args: { id: 'email', label: 'メールアドレス' },
}

export default meta
type Story = StoryObj<typeof Field>

export const Default: Story = {
  render: (args) => (
    <Field {...args}>
      <Input id={args.id} type="email" />
    </Field>
  ),
}

export const WithError: Story = {
  args: { error: '必須項目です。' },
  render: (args) => (
    <Field {...args}>
      <Input id={args.id} type="email" aria-describedby="email-error" aria-invalid />
    </Field>
  ),
}
