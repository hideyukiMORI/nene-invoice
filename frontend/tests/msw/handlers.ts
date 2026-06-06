import { http, HttpResponse } from 'msw'
import { buildClientDto } from '@tests/factories/client'
import { buildInvoiceDto, buildInvoiceWithLinesDto } from '@tests/factories/invoice'

/** Default happy-path handlers mirroring the OpenAPI contract. */
export const handlers = [
  http.post('/auth/login', () => HttpResponse.json({ token: 'test-token' })),

  http.get('/admin/me', () =>
    HttpResponse.json({ id: 1, email: 'admin@example.com', role: 'admin', organization_id: 1 }),
  ),

  http.get('/admin/dashboard', () =>
    HttpResponse.json({
      unpaid_count: 0,
      overdue_count: 0,
      outstanding_total_cents: 0,
      recent_unpaid: [],
      received_this_month_cents: 0,
      received_last_month_cents: 0,
      aging: { current: 0, overdue_1_30: 0, overdue_31_plus: 0 },
      billed_this_month_cents: 0,
      billed_last_month_cents: 0,
      monthly_billed: [],
      billed_prev_year_month_cents: 0,
      billed_daily_current: [],
      billed_daily_prev_month: [],
    }),
  ),

  http.get('/admin/company-settings', () =>
    HttpResponse.json({
      organization_id: 1,
      legal_name: 'テスト株式会社',
      address: null,
      phone: null,
      email: null,
      registration_number: null,
      bank_name: null,
      bank_branch: null,
      account_type: null,
      account_number: null,
    }),
  ),

  http.put('/admin/company-settings', () =>
    HttpResponse.json({
      organization_id: 1,
      legal_name: 'テスト株式会社',
      address: null,
      phone: null,
      email: null,
      registration_number: null,
      bank_name: null,
      bank_branch: null,
      account_type: null,
      account_number: null,
    }),
  ),

  http.get('/admin/users', () =>
    HttpResponse.json({
      items: [
        {
          id: 1,
          email: 'admin@example.com',
          role: 'admin',
          organization_id: 1,
          status: 'active',
          created_at: '2026-05-01 00:00:00',
          updated_at: '2026-05-01 00:00:00',
        },
      ],
      total: 1,
      limit: 100,
      offset: 0,
    }),
  ),

  http.get('/admin/users/:id', () =>
    HttpResponse.json({
      id: 1,
      email: 'admin@example.com',
      role: 'admin',
      organization_id: 1,
      status: 'active',
      created_at: '2026-05-01 00:00:00',
      updated_at: '2026-05-01 00:00:00',
    }),
  ),

  http.get('/admin/clients', () =>
    HttpResponse.json({ items: [buildClientDto()], total: 1, limit: 100, offset: 0 }),
  ),

  http.get('/admin/items', () =>
    HttpResponse.json({
      items: [
        {
          id: 1,
          organization_id: 1,
          description: '保守サポート（月額）',
          default_unit_price_cents: 50000,
          default_tax_rate_bps: 1000,
        },
      ],
      total: 1,
      limit: 100,
      offset: 0,
    }),
  ),

  http.get('/admin/templates', () =>
    HttpResponse.json({
      items: [
        { id: 1, organization_id: 1, name: '月次保守テンプレート', notes: '毎月', line_items: [] },
      ],
      total: 1,
      limit: 50,
      offset: 0,
    }),
  ),

  http.get('/admin/quotes', () => HttpResponse.json({ items: [], total: 0, limit: 20, offset: 0 })),

  http.post('/admin/quotes', () =>
    HttpResponse.json(
      {
        id: 1,
        organization_id: 1,
        client_id: 5,
        quote_number: 'EST-2026-001',
        status: 'draft',
        subtotal_cents: 100000,
        tax_cents: 10000,
        total_cents: 110000,
        line_items: [],
      },
      { status: 201 },
    ),
  ),

  http.get('/admin/quotes/:id', () =>
    HttpResponse.json({
      id: 1,
      organization_id: 1,
      client_id: 5,
      quote_number: 'EST-2026-001',
      status: 'draft',
      subtotal_cents: 100000,
      tax_cents: 10000,
      total_cents: 110000,
      line_items: [],
    }),
  ),

  http.patch('/admin/quotes/:id', () =>
    HttpResponse.json({
      id: 1,
      organization_id: 1,
      client_id: 5,
      quote_number: 'EST-2026-001',
      status: 'sent',
      subtotal_cents: 100000,
      tax_cents: 10000,
      total_cents: 110000,
      line_items: [],
    }),
  ),

  http.post('/admin/quotes/:id/convert', () =>
    HttpResponse.json(
      {
        id: 10,
        organization_id: 1,
        client_id: 5,
        is_overdue: false,
        status: 'draft',
        is_qualified_invoice: false,
        invoice_number: null,
        subtotal_cents: 100000,
        tax_cents: 10000,
        total_cents: 110000,
        line_items: [],
      },
      { status: 201 },
    ),
  ),

  http.get('/admin/invoices', () =>
    HttpResponse.json({ items: [buildInvoiceDto()], total: 1, limit: 20, offset: 0 }),
  ),

  http.post('/admin/invoices', () =>
    HttpResponse.json(buildInvoiceWithLinesDto(), { status: 201 }),
  ),

  http.get('/admin/invoices/:id', () => HttpResponse.json(buildInvoiceWithLinesDto())),

  http.post('/admin/invoices/:id/issue', () => HttpResponse.json(buildInvoiceWithLinesDto())),

  http.get('/admin/invoices/:id/payments', () =>
    HttpResponse.json({ items: [], total_paid_cents: 0 }),
  ),

  http.post('/admin/invoices/:id/payments', () =>
    HttpResponse.json(
      {
        payment: {
          id: 1,
          organization_id: 1,
          invoice_id: 1,
          amount_cents: 50000,
          paid_at: '2026-05-30 10:00:00',
          method: 'bank_transfer',
          note: null,
        },
        invoice: buildInvoiceDto({ status: 'partially_paid' }),
        total_paid_cents: 50000,
      },
      { status: 201 },
    ),
  ),
]
