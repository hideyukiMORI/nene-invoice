import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { toInvoiceId } from '@/entities/invoice'
import { server } from '@tests/msw/server'
import { buildInvoiceWithLinesDto } from '@tests/factories/invoice'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import { useIssueInvoice } from './use-issue-invoice'

/** A draft invoice is the only issuable state, so every #771 case needs one. */
function draftInvoice(): void {
  server.use(
    http.get('/admin/invoices/:id', () =>
      HttpResponse.json(buildInvoiceWithLinesDto({ status: 'draft', invoice_number: null })),
    ),
  )
}

function companyRegistrationNumber(value: string | null): void {
  server.use(
    http.get('/admin/company-settings', () =>
      HttpResponse.json({
        organization_id: 1,
        legal_name: 'テスト株式会社',
        address: null,
        phone: null,
        email: null,
        registration_number: value,
        bank_name: null,
        bank_branch: null,
        account_type: null,
        account_number: null,
      }),
    ),
  )
}

/** Captures the body the issue endpoint actually receives. */
function captureIssueBody(): { body: Record<string, unknown> | null } {
  const captured: { body: Record<string, unknown> | null } = { body: null }
  server.use(
    http.post('/admin/invoices/:id/issue', async ({ request }) => {
      captured.body = (await request.json()) as Record<string, unknown>
      return HttpResponse.json(buildInvoiceWithLinesDto({ status: 'issued' }))
    }),
  )
  return captured
}

describe('useIssueInvoice (feature)', () => {
  it('allows issuing a draft invoice', async () => {
    draftInvoice()

    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.canIssue).toBe(true)
    })
  })

  it('hides the action for an already-issued invoice', async () => {
    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.canIssue).toBe(false)
    })
  })

  // ── #771 ─────────────────────────────────────────────────────────────────
  // The issuer without a T-number could not issue anything: the UI only knew
  // how to send `qualified: true`, which the backend refuses when the
  // registration number is empty (accounting-compliance.md §4).

  it('issues as NOT qualified when the issuer has no registration number', async () => {
    draftInvoice()
    companyRegistrationNumber(null)
    const captured = captureIssueBody()

    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.qualified).toBe(false)
    })

    act(() => {
      result.current.issue()
    })

    await waitFor(() => {
      expect(captured.body).not.toBeNull()
    })
    expect(captured.body).toMatchObject({ qualified: false })
  })

  it('issues as qualified when the issuer has a registration number', async () => {
    draftInvoice()
    companyRegistrationNumber('T1010001011111')
    const captured = captureIssueBody()

    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.qualified).toBe(true)
    })

    act(() => {
      result.current.issue()
    })

    await waitFor(() => {
      expect(captured.body).not.toBeNull()
    })
    expect(captured.body).toMatchObject({ qualified: true })
  })

  it('treats an empty registration number as no registration number', async () => {
    // Both API boundaries collapse '' to null today, so this cannot arrive from
    // the server as things stand. The case is pinned because the alternative —
    // '' read as "has a number" — would issue a 適格請求書 naming an empty
    // registration number, which §4 forbids.
    draftInvoice()
    companyRegistrationNumber('')

    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.qualified).toBe(false)
    })
  })

  it('does not issue while the issuer eligibility is still unknown', async () => {
    draftInvoice()
    // A company-settings request that never settles: `qualified` stays null.
    server.use(http.get('/admin/company-settings', () => new Promise(() => {})))
    const captured = captureIssueBody()

    const { result } = renderHookWithProviders(() => useIssueInvoice(toInvoiceId(1)))

    await waitFor(() => {
      expect(result.current.canIssue).toBe(true)
    })
    expect(result.current.qualified).toBeNull()

    act(() => {
      result.current.issue()
    })

    // Nothing was sent — an unresolved query is not a measurement, and issuing
    // is irreversible.
    await new Promise((resolve) => setTimeout(resolve, 50))
    expect(captured.body).toBeNull()
  })
})
