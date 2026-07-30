import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { CreateRecurringInvoiceForm } from './CreateRecurringInvoiceForm'

// jsdom navigator.language defaults to en-US, so the form renders the en catalog.
describe('CreateRecurringInvoiceForm', () => {
  it('renders the schedule header with the frequency options', () => {
    renderWithProviders(<CreateRecurringInvoiceForm />)

    expect(screen.getByRole('heading', { name: 'New recurring invoice' })).toBeTruthy()
    const frequency = screen.getByLabelText('Frequency')
    expect(frequency).toBeTruthy()
    expect(frequency.textContent).toContain('Monthly')
    expect(frequency.textContent).toContain('Quarterly')
  })

  it('shows a validation error when required fields are empty', async () => {
    const user = userEvent.setup()
    renderWithProviders(<CreateRecurringInvoiceForm />)

    await user.click(screen.getByRole('button', { name: 'Create' }))

    expect(await screen.findByText('Please enter a name.')).toBeTruthy()
  })

  it('lets the operator add and remove line rows', async () => {
    const user = userEvent.setup()
    renderWithProviders(<CreateRecurringInvoiceForm />)

    expect(screen.getAllByLabelText('Qty')).toHaveLength(1)

    await user.click(screen.getByRole('button', { name: /Add line/ }))

    await waitFor(() => {
      expect(screen.getAllByLabelText('Qty')).toHaveLength(2)
    })
  })
})
