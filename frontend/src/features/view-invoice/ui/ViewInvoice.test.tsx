import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toInvoiceId } from '@/entities/invoice'
import { server } from '@tests/msw/server'
import { buildClientDto } from '@tests/factories/client'
import { buildInvoiceWithLinesDto } from '@tests/factories/invoice'
import { renderWithProviders } from '@tests/render/render-with-providers'
import { ViewInvoice } from './ViewInvoice'

function problem(slug: string, status: number) {
  return new HttpResponse(
    JSON.stringify({
      type: `https://nene-invoice.dev/problems/${slug}`,
      title: 'error',
      status,
    }),
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  )
}

// jsdom navigator.language defaults to en-US, so the component renders the en
// catalog (see SignInForm.test.tsx). The default invoice/client factories
// (status "issued", client id 5) make "Send by email" available and satisfy
// the client detail fetch the document view always makes.
describe('ViewInvoice — send-email failure banner (#650)', () => {
  it('shows the mail-transport copy for a 502 email-delivery-failed response, not the client-address copy', async () => {
    server.use(
      http.get('/admin/invoices/:id', () => HttpResponse.json(buildInvoiceWithLinesDto())),
      http.get('/admin/clients/:id', () => HttpResponse.json(buildClientDto())),
      http.post('/admin/invoices/:id/send-email', () => problem('email-delivery-failed', 502)),
    )

    const user = userEvent.setup()
    const { findByRole, getByRole } = renderWithProviders(
      <ViewInvoice invoiceId={toInvoiceId(1)} />,
    )

    await user.click(await findByRole('button', { name: 'Send by email' }))

    const alert = await findByRole('alert')
    expect(alert).toHaveTextContent(
      'We couldn\'t reach the mail server, so the email wasn\'t sent. Try "Resend" again in a moment; if it keeps failing, contact your system administrator.',
    )
    // Not the client-address message — that would misdirect the user (#650).
    expect(alert).not.toHaveTextContent("The client's email address")
    expect(getByRole('button', { name: 'Check client' })).toBeInTheDocument()
  })

  it('shows the client-address copy for a 422 client-email-missing response', async () => {
    server.use(
      http.get('/admin/invoices/:id', () => HttpResponse.json(buildInvoiceWithLinesDto())),
      http.get('/admin/clients/:id', () => HttpResponse.json(buildClientDto())),
      http.post('/admin/invoices/:id/send-email', () => problem('client-email-missing', 422)),
    )

    const user = userEvent.setup()
    const { findByRole } = renderWithProviders(<ViewInvoice invoiceId={toInvoiceId(1)} />)

    await user.click(await findByRole('button', { name: 'Send by email' }))

    const alert = await findByRole('alert')
    expect(alert).toHaveTextContent(
      "The client's email address may be missing or malformed. Please check the address and try again.",
    )
    expect(alert).not.toHaveTextContent("We couldn't reach the mail server")
  })
})

describe('ViewInvoice — demo email preview (#626)', () => {
  it('opens the preview modal (recipient / subject / body + "not sent" notice) on a preview response', async () => {
    server.use(
      http.get('/admin/invoices/:id', () => HttpResponse.json(buildInvoiceWithLinesDto())),
      http.get('/admin/clients/:id', () => HttpResponse.json(buildClientDto())),
      http.post('/admin/invoices/:id/send-email', () =>
        HttpResponse.json({
          preview: true,
          recipient: 'buyer@example.com',
          subject: '請求書 INV-2026-001 — テスト商会',
          body_html: '<p>株式会社サンプル 様</p><p>添付の PDF をご確認ください。</p>',
        }),
      ),
    )

    const user = userEvent.setup()
    const { findByRole } = renderWithProviders(<ViewInvoice invoiceId={toInvoiceId(1)} />)

    await user.click(await findByRole('button', { name: 'Send by email' }))

    const dialog = await findByRole('dialog')
    expect(dialog).toHaveTextContent('Email preview')
    // The "no email was actually sent" demo notice must be shown.
    expect(dialog).toHaveTextContent('No email was actually sent')
    expect(dialog).toHaveTextContent('buyer@example.com')
    expect(dialog).toHaveTextContent('請求書 INV-2026-001 — テスト商会')
    expect(dialog).toHaveTextContent('株式会社サンプル 様')
  })

  it('shows the success toast (not the modal) on a 204 real send', async () => {
    server.use(
      http.get('/admin/invoices/:id', () => HttpResponse.json(buildInvoiceWithLinesDto())),
      http.get('/admin/clients/:id', () => HttpResponse.json(buildClientDto())),
      http.post('/admin/invoices/:id/send-email', () => new HttpResponse(null, { status: 204 })),
    )

    const user = userEvent.setup()
    const { findByRole, findByText, queryByRole } = renderWithProviders(
      <ViewInvoice invoiceId={toInvoiceId(1)} />,
    )

    await user.click(await findByRole('button', { name: 'Send by email' }))

    // Success toast (role="status") appears; no preview dialog is opened.
    expect(await findByText('Email sent')).toBeInTheDocument()
    expect(queryByRole('dialog')).not.toBeInTheDocument()
  })
})
