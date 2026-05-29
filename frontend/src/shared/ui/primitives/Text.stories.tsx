/**
 * Text — typographic primitive.
 *
 * In:  as (element), variant, children
 * Out: none
 *
 * Does not: fetch data or carry domain meaning.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { Text } from './Text'

const meta: Meta<typeof Text> = {
  title: 'Primitives/Text',
  component: Text,
  args: { children: 'NeNe Invoice' },
}

export default meta
type Story = StoryObj<typeof Text>

export const Body: Story = { args: { variant: 'body' } }
export const Muted: Story = { args: { variant: 'muted' } }
export const HeadingMd: Story = {
  args: { as: 'h1', variant: 'heading-md', children: '請求書一覧' },
}
