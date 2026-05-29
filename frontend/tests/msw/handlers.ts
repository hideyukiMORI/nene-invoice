import { http, HttpResponse } from 'msw'
import { buildClientDto } from '@tests/factories/client'
import { buildInvoiceDto, buildInvoiceWithLinesDto } from '@tests/factories/invoice'

/** Default happy-path handlers mirroring the OpenAPI contract. */
export const handlers = [
  http.post('/auth/login', () => HttpResponse.json({ token: 'test-token' })),

  http.get('/admin/me', () =>
    HttpResponse.json({ id: 1, email: 'admin@example.com', role: 'admin', organization_id: 1 }),
  ),

  http.get('/admin/clients', () =>
    HttpResponse.json({ items: [buildClientDto()], total: 1, limit: 100, offset: 0 }),
  ),

  http.get('/admin/invoices', () =>
    HttpResponse.json({ items: [buildInvoiceDto()], total: 1, limit: 20, offset: 0 }),
  ),

  http.post('/admin/invoices', () =>
    HttpResponse.json(buildInvoiceWithLinesDto(), { status: 201 }),
  ),

  http.get('/admin/invoices/:id', () => HttpResponse.json(buildInvoiceWithLinesDto())),

  http.post('/admin/invoices/:id/issue', () => HttpResponse.json(buildInvoiceWithLinesDto())),
]
