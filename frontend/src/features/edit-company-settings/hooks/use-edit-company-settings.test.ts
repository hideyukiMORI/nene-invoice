import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useEditCompanySettings } from './use-edit-company-settings'

describe('useEditCompanySettings', () => {
  it('loads the current settings and prefills the form', async () => {
    const { result } = renderHookWithProviders(() => useEditCompanySettings())
    expect(result.current.kind).toBe('loading')

    await waitFor(() => {
      expect(result.current.kind).toBe('ready')
    })

    if (result.current.kind === 'ready') {
      expect(result.current.form.getValues('legal_name')).toBe('テスト株式会社')
      // null fields from the API become empty strings in the form.
      expect(result.current.form.getValues('address')).toBe('')
      expect(result.current.savedMessage).toBeNull()
    }
  })

  it('exposes an error state when settings cannot be loaded', async () => {
    server.use(http.get('/admin/company-settings', () => new HttpResponse(null, { status: 500 })))

    const { result } = renderHookWithProviders(() => useEditCompanySettings())
    await waitFor(() => {
      expect(result.current.kind).toBe('error')
    })
  })
})
