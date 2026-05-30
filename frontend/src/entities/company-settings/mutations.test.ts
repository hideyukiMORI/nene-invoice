import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import type { UpdateCompanySettingsInput } from './model'
import { useUpdateCompanySettings } from './mutations'

const input: UpdateCompanySettingsInput = {
  legal_name: '株式会社あやね',
  address: null,
  phone: null,
  email: null,
  registration_number: 'T1234567890123',
  bank_name: null,
  bank_branch: null,
  account_type: null,
  account_number: null,
}

describe('useUpdateCompanySettings', () => {
  it('puts and returns the mapped settings', async () => {
    server.use(
      http.put('/admin/company-settings', () =>
        HttpResponse.json({
          organization_id: 1,
          legal_name: '株式会社あやね',
          registration_number: 'T1234567890123',
        }),
      ),
    )

    const { result } = renderHookWithProviders(() => useUpdateCompanySettings())
    result.current.mutate(input)

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.legal_name).toBe('株式会社あやね')
    expect(result.current.data?.registration_number).toBe('T1234567890123')
    expect(result.current.data?.address).toBeNull()
  })

  it('surfaces an AppError on a 422 invalid registration number', async () => {
    server.use(
      http.put(
        '/admin/company-settings',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/invalid-registration-number',
              title: 'Validation Failed',
              status: 422,
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useUpdateCompanySettings())
    result.current.mutate({ ...input, registration_number: 'BAD' })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('invalid-registration-number')
  })
})
