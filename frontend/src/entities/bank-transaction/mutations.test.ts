import { waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '@tests/msw/server'
import { renderHookWithProviders } from '@tests/render/render-with-providers'
import {
  buildBankConfirmResultDto,
  buildBankImportResultDto,
  buildBankTransactionDto,
} from '@tests/factories/bank-transaction'
import { toBankTransactionId } from './ids'
import { useConfirmBankMatch, useIgnoreBankTransaction, useImportBankCsv } from './mutations'

describe('useImportBankCsv', () => {
  it('posts raw bytes and returns the mapped report', async () => {
    server.use(
      http.post('/admin/bank-transactions/import', () =>
        HttpResponse.json(buildBankImportResultDto({ imported_count: 5 })),
      ),
    )

    const { result } = renderHookWithProviders(() => useImportBankCsv())
    result.current.mutate({
      file: new Blob(['a,b,c'], { type: 'text/csv' }),
      preset: 'net_bank_credit_debit',
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.imported_count).toBe(5)
  })

  it('resolves the 422 format-error report instead of throwing', async () => {
    server.use(
      http.post('/admin/bank-transactions/import', () =>
        HttpResponse.json(
          buildBankImportResultDto({ imported_count: 0, format_error: 'unknown header' }),
          { status: 422 },
        ),
      ),
    )

    const { result } = renderHookWithProviders(() => useImportBankCsv())
    result.current.mutate({
      file: new Blob(['bad'], { type: 'text/csv' }),
      preset: 'signed_amount',
    })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.format_error).toBe('unknown header')
  })
})

describe('useConfirmBankMatch', () => {
  it('posts the chosen invoice and returns the mapped result', async () => {
    server.use(
      http.post('/admin/bank-transactions/:id/confirm', () =>
        HttpResponse.json(buildBankConfirmResultDto()),
      ),
    )

    const { result } = renderHookWithProviders(() => useConfirmBankMatch())
    result.current.mutate({ id: toBankTransactionId(42), invoice_id: 10 })

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.payment.invoice_id).toBe(10)
    expect(result.current.data?.transaction.status).toBe('posted')
  })

  it('surfaces an AppError on a 409 conflict', async () => {
    server.use(
      http.post(
        '/admin/bank-transactions/:id/confirm',
        () =>
          new HttpResponse(
            JSON.stringify({
              type: 'https://nene-invoice.dev/problems/transaction-already-posted',
              title: 'Already posted',
              status: 409,
            }),
            { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      ),
    )

    const { result } = renderHookWithProviders(() => useConfirmBankMatch())
    result.current.mutate({ id: toBankTransactionId(42), invoice_id: 10 })

    await waitFor(() => {
      expect(result.current.isError).toBe(true)
    })
    expect(result.current.error?.slug).toBe('transaction-already-posted')
  })
})

describe('useIgnoreBankTransaction', () => {
  it('posts and returns the updated transaction', async () => {
    server.use(
      http.post('/admin/bank-transactions/:id/ignore', () =>
        HttpResponse.json(buildBankTransactionDto({ status: 'ignored' })),
      ),
    )

    const { result } = renderHookWithProviders(() => useIgnoreBankTransaction())
    result.current.mutate(toBankTransactionId(42))

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true)
    })
    expect(result.current.data?.status).toBe('ignored')
  })
})
