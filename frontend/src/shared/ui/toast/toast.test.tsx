import { act, fireEvent } from '@testing-library/react'
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
    const { getByText, queryByText } = renderWithProviders(<Harness />)

    fireEvent.click(getByText('notify-ok'))
    const toast = getByText('Email sent')
    expect(toast).toBeInTheDocument()
    expect(toast.closest('.toast')).toHaveAttribute('role', 'status')

    act(() => {
      vi.advanceTimersByTime(5000)
    })
    expect(queryByText('Email sent')).not.toBeInTheDocument()
  })

  it('shows an error toast with role="alert"', () => {
    const { getByText } = renderWithProviders(<Harness />)

    fireEvent.click(getByText('notify-err'))
    expect(getByText('Connection failed').closest('.toast')).toHaveAttribute('role', 'alert')
  })

  it('dismisses manually via the close button before the timer fires', () => {
    const { getByText, getByLabelText, queryByText } = renderWithProviders(<Harness />)

    fireEvent.click(getByText('notify-ok'))
    expect(getByText('Email sent')).toBeInTheDocument()

    fireEvent.click(getByLabelText('Close'))
    expect(queryByText('Email sent')).not.toBeInTheDocument()
  })
})
