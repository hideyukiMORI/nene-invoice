import { waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { CreateRecurringInvoiceForm } from './CreateRecurringInvoiceForm'

// jsdom navigator.language defaults to en-US, so the form renders the en catalog.
describe('CreateRecurringInvoiceForm', () => {
  it('renders the schedule header with the frequency options', () => {
    const { getByRole, getByLabelText } = renderWithProviders(<CreateRecurringInvoiceForm />)

    expect(getByRole('heading', { name: 'New recurring invoice' })).toBeTruthy()
    const frequency = getByLabelText('Frequency')
    expect(frequency).toBeTruthy()
    expect(frequency.textContent).toContain('Monthly')
    expect(frequency.textContent).toContain('Quarterly')
  })

  it('shows a validation error when required fields are empty', async () => {
    const user = userEvent.setup()
    const { getByRole, findByText } = renderWithProviders(<CreateRecurringInvoiceForm />)

    await user.click(getByRole('button', { name: 'Create' }))

    expect(await findByText('Please enter a name.')).toBeTruthy()
  })

  it('lets the operator add and remove line rows', async () => {
    const user = userEvent.setup()
    const { getAllByLabelText, getByRole } = renderWithProviders(<CreateRecurringInvoiceForm />)

    expect(getAllByLabelText('Qty')).toHaveLength(1)

    await user.click(getByRole('button', { name: /Add line/ }))

    await waitFor(() => {
      expect(getAllByLabelText('Qty')).toHaveLength(2)
    })
  })
})
