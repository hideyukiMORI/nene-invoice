import { screen, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { ReconcileBank } from './ReconcileBank'

describe('ReconcileBank', () => {
  it('renders the import panel, the status filter, and a staged transaction row', async () => {
    renderWithProviders(<ReconcileBank />)

    // Two comboboxes: the import preset select + the workbench status filter
    // (locale-independent — jsdom resolves the locale from navigator).
    expect(screen.getAllByRole('combobox')).toHaveLength(2)

    // Workbench: the seeded credit line appears with its payer name and amount
    // (data-driven, not translated).
    await waitFor(() => {
      expect(screen.getByText('カ）トリヒキサキ')).toBeInTheDocument()
    })
    expect(screen.getByText('¥110,000')).toBeInTheDocument()
  })
})
