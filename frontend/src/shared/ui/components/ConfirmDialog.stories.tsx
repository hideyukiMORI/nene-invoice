/**
 * ConfirmDialog — modal confirmation for destructive / irreversible actions.
 *
 * In:  title, message, confirmLabel, cancelLabel, pending, destructive
 * Out: onConfirm(), onCancel()
 *
 * Does not: fetch, know what is being confirmed, or own open/close state.
 */
import type { Meta, StoryObj } from '@storybook/react-vite'
import { ConfirmDialog } from './ConfirmDialog'

const noop = (): void => undefined

const meta: Meta<typeof ConfirmDialog> = {
  title: 'Components/ConfirmDialog',
  component: ConfirmDialog,
  args: {
    title: '取引先を削除しますか？',
    message: 'この操作は取り消せません。',
    confirmLabel: '削除',
    cancelLabel: 'キャンセル',
    onConfirm: noop,
    onCancel: noop,
  },
}

export default meta
type Story = StoryObj<typeof ConfirmDialog>

export const Destructive: Story = {}
export const Pending: Story = { args: { pending: true } }
export const NonDestructive: Story = {
  args: { destructive: false, title: 'Proceed?', confirmLabel: 'OK' },
}
