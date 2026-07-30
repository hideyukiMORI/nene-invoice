import { act, fireEvent, screen, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { useToast } from './context'

function Harness() {
  const { showToast } = useToast()
  return (
    <div>
      <button
        type="button"
        onClick={() => {
          showToast({ tone: 'ok', title: 'Email sent', description: 'Sent to Acme.' })
        }}
      >
        notify-ok
      </button>
      <button
        type="button"
        onClick={() => {
          showToast({
            tone: 'err',
            title: 'Connection failed',
            action: { label: 'Retry', onClick: vi.fn() },
          })
        }}
      >
        notify-err
      </button>
    </div>
  )
}

describe('Toast system', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })
  afterEach(() => {
    vi.runOnlyPendingTimers()
    vi.useRealTimers()
  })

  it('shows a success toast with role="status" and auto-dismisses', () => {
    renderWithProviders(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: 'notify-ok' }))
    // role="status" is the toast element itself, so finding it by role proves
    // both that the toast rendered and that it carries the gentle live region.
    expect(within(screen.getByRole('status')).getByText('Email sent')).toBeInTheDocument()

    act(() => {
      vi.advanceTimersByTime(5000)
    })
    expect(screen.queryByText('Email sent')).not.toBeInTheDocument()
  })

  it('shows an error toast with role="alert"', () => {
    renderWithProviders(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: 'notify-err' }))
    expect(within(screen.getByRole('alert')).getByText('Connection failed')).toBeInTheDocument()
  })

  it('dismisses manually via the close button before the timer fires', () => {
    renderWithProviders(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: 'notify-ok' }))
    expect(screen.getByText('Email sent')).toBeInTheDocument()

    fireEvent.click(screen.getByLabelText('Close'))
    expect(screen.queryByText('Email sent')).not.toBeInTheDocument()
  })
})
