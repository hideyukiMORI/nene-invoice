import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toClientId } from '@/entities/client'
import { server } from '@tests/msw/server'
import { buildClientDto } from '@tests/factories/client'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useEditClient } from './use-edit-client'

describe('useEditClient (feature)', () => {
  it('loads the client and prefills the form', async () => {
    server.use(http.get('/admin/clients/:id', () => HttpResponse.json(buildClientDto())))

    const { result } = renderHookWithProviders(() => useEditClient(toClientId(5)))

    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.form.getValues('name')).toBe('得意先ABC')
      expect(result.current.form.getValues('registration_number')).toBe('T9876543210123')
    }
  })

  it('exposes an error state when the client cannot be loaded', async () => {
    server.use(http.get('/admin/clients/:id', () => new HttpResponse(null, { status: 404 })))

    const { result } = renderHookWithProviders(() => useEditClient(toClientId(99)))

    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
