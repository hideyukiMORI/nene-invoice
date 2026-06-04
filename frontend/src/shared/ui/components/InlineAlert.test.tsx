import { render } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { InlineAlert } from './InlineAlert'

describe('InlineAlert', () => {
  it('uses role="alert" for errors and shows the message', () => {
    const { getByRole } = render(<InlineAlert tone="error" message="Could not issue." />)
    expect(getByRole('alert')).toHaveTextContent('Could not issue.')
  })

  it('uses role="status" for success and info tones', () => {
    const { getByRole, rerender } = render(<InlineAlert tone="success" message="Saved." />)
    expect(getByRole('status')).toHaveTextContent('Saved.')

    rerender(<InlineAlert tone="info" message="Heads up." />)
    expect(getByRole('status')).toHaveTextContent('Heads up.')
  })

  it('renders a recover link that invokes its handler', async () => {
    const user = userEvent.setup()
    const onRecover = vi.fn()

    const { getByRole } = render(
      <InlineAlert
        tone="error"
        message="Could not issue."
        recover={{ label: 'Open company settings', onClick: onRecover }}
      />,
    )

    await user.click(getByRole('button', { name: 'Open company settings' }))
    expect(onRecover).toHaveBeenCalledOnce()
  })
})
