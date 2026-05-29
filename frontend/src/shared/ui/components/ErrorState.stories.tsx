/**
 * ErrorState — safe error panel with retry.
 *
 * In:  message, retryLabel
 * Out: onRetry()
 *
 * Does not: parse errors or know the failed operation.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { ErrorState } from './ErrorState'

const noop = (): void => undefined

const meta: Meta<typeof ErrorState> = {
  title: 'Components/ErrorState',
  component: ErrorState,
  args: { message: '請求書を取得できませんでした。', retryLabel: '再試行', onRetry: noop },
}

export default meta
type Story = StoryObj<typeof ErrorState>

export const Default: Story = {}
