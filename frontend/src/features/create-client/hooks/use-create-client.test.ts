import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import type { SyntheticEvent } from 'react'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useCreateClient } from './use-create-client'

const fakeSubmitEvent = { preventDefault: () => {} } as unknown as SyntheticEvent

describe('useCreateClient', () => {
  it('has no error message before submitting', () => {
    const { result } = renderHookWithProviders(() => useCreateClient())
    expect(result.current.errorMessage).toBeNull()
    expect(result.current.isPending).toBe(false)
  })

  it('surfaces an error message when creation fails', async () => {
    server.use(
      http.post(
        '/admin/clients',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/validation-failed',
              title: 'Validation Failed',
              status: 422,
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useCreateClient())

    act(() => {
      result.current.form.setValue('name', '新規取引先')
      result.current.onSubmit(fakeSubmitEvent)
    })

    await waitFor(() => {
      expect(result.current.errorMessage).not.toBeNull()
    })
  })
})
