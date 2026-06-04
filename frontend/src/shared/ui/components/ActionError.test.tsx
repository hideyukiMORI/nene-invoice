import { render } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ActionError } from './ActionError'

describe('ActionError', () => {
  it('announces the error with role="alert" and shows title + description', () => {
    const { getByRole, getByText } = render(
      <ActionError title="Couldn't send the email" description="Check the address and retry." />,
    )

    const alert = getByRole('alert')
    expect(alert).toHaveTextContent("Couldn't send the email")
    expect(getByText('Check the address and retry.')).toBeInTheDocument()
  })

  it('renders recovery actions and invokes their handlers', async () => {
    const user = userEvent.setup()
    const onRetry = vi.fn()
    const onCheck = vi.fn()

    const { getByRole } = render(
      <ActionError
        title="Failed"
        description="Try again."
        actions={[
          { label: 'Resend', variant: 'solid', onClick: onRetry },
          { label: 'Check client', variant: 'outline', onClick: onCheck },
        ]}
      />,
    )

    await user.click(getByRole('button', { name: 'Resend' }))
    await user.click(getByRole('button', { name: 'Check client' }))

    expect(onRetry).toHaveBeenCalledOnce()
    expect(onCheck).toHaveBeenCalledOnce()
  })

  it('renders a dismiss button only when onClose is provided', async () => {
    const user = userEvent.setup()
    const onClose = vi.fn()

    const { getByRole } = render(
      <ActionError title="Failed" description="Try again." onClose={onClose} closeLabel="Close" />,
    )

    await user.click(getByRole('button', { name: 'Close' }))
    expect(onClose).toHaveBeenCalledOnce()
  })
})
