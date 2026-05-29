import { http, HttpResponse } from 'msw'
import { buildInvoiceDto } from '@tests/factories/invoice'

/** Default happy-path handlers mirroring the OpenAPI contract. */
export const handlers = [
  http.post('/auth/login', () => HttpResponse.json({ token: 'test-token' })),

  http.get('/admin/me', () =>
    HttpResponse.json({ id: 1, email: 'admin@example.com', role: 'admin', organization_id: 1 }),
  ),

  http.get('/admin/invoices', () =>
    HttpResponse.json({ items: [buildInvoiceDto()], total: 1, limit: 20, offset: 0 }),
  ),
]
