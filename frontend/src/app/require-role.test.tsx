import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { setAuthToken } from '@/shared/api/client'
import { I18nProvider } from '@/shared/i18n'
import { server } from '@tests/msw/server'
import { RequireRole } from './require-role'

function meIs(role: string, organizationId: number | null): void {
  server.use(
    http.get('/admin/me', () =>
      HttpResponse.json({ id: 1, email: 'x@example.com', role, organization_id: organizationId }),
    ),
  )
}

function renderAt(path: string) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <I18nProvider>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[path]}>
          <Routes>
            <Route element={<RequireRole audience="org" />}>
              <Route path="/dashboard" element={<div>DASHBOARD PAGE</div>} />
            </Route>
            <Route element={<RequireRole audience="superadmin" />}>
              <Route path="/organizations" element={<div>ORGANIZATIONS PAGE</div>} />
            </Route>
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </I18nProvider>,
  )
}

describe('RequireRole (型B Phase 2 per-route guard)', () => {
  beforeEach(() => {
    setAuthToken('seed')
  })
  afterEach(() => {
    setAuthToken(null)
  })

  it('redirects an org-less superadmin from an org-scoped route to organization management', async () => {
    meIs('superadmin', null)
    renderAt('/dashboard')

    expect(await screen.findByText('ORGANIZATIONS PAGE')).toBeInTheDocument()
    expect(screen.queryByText('DASHBOARD PAGE')).not.toBeInTheDocument()
  })

  it('redirects a tenant operator from the superadmin-only organization route to the dashboard', async () => {
    meIs('admin', 1)
    renderAt('/organizations')

    expect(await screen.findByText('DASHBOARD PAGE')).toBeInTheDocument()
    expect(screen.queryByText('ORGANIZATIONS PAGE')).not.toBeInTheDocument()
  })

  it('lets a tenant operator use org-scoped routes', async () => {
    meIs('admin', 1)
    renderAt('/dashboard')

    expect(await screen.findByText('DASHBOARD PAGE')).toBeInTheDocument()
  })

  it('lets a superadmin use organization management', async () => {
    meIs('superadmin', null)
    renderAt('/organizations')

    expect(await screen.findByText('ORGANIZATIONS PAGE')).toBeInTheDocument()
  })
})
