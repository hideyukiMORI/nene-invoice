/**
 * EmptyState — intentional "no results" panel.
 *
 * In:  message
 * Out: none
 *
 * Does not: fetch or decide emptiness — the feature passes the copy.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { EmptyState } from './EmptyState'

const meta: Meta<typeof EmptyState> = {
  title: 'Components/EmptyState',
  component: EmptyState,
  args: { message: '請求書がまだありません。' },
}

export default meta
type Story = StoryObj<typeof EmptyState>

export const Default: Story = {}
