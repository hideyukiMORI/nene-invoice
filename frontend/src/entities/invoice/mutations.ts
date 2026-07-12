import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { InvoiceWithLinesDto } from './api-types'
import { toInvoiceWithLines } from './mapper'
import type { CreateInvoiceInput, InvoiceWithLines, IssueInvoiceInput } from './model'
import { invoiceKeys } from './query-keys'

/**
 * POST /admin/invoices — creates a draft invoice (tax/totals computed by the API).
 * Invalidates the invoice lists on success so the new draft appears.
 */
export function useCreateInvoice(): UseMutationResult<
  InvoiceWithLines,
  AppError,
  CreateInvoiceInput
> {
  const queryClient = useQueryClient()

  return useMutation<InvoiceWithLines, AppError, CreateInvoiceInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<InvoiceWithLinesDto>('/admin/invoices', {
        client_id: input.client_id,
        line_items: input.line_items.map((line) => ({
          description: line.description,
          quantity: line.quantity,
          unit_price_cents: line.unit_price_cents,
          tax_rate_bps: line.tax_rate_bps,
        })),
        notes: input.notes,
      })
      return toInvoiceWithLines(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.lists() })
    },
  })
}

/**
 * POST /admin/invoices/{id}/issue — issues a draft invoice (allocates the INV
 * number, locks it). Invalidates the detail and lists so the new state shows.
 */
export function useIssueInvoice(): UseMutationResult<
  InvoiceWithLines,
  AppError,
  IssueInvoiceInput
> {
  const queryClient = useQueryClient()

  return useMutation<InvoiceWithLines, AppError, IssueInvoiceInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<InvoiceWithLinesDto>(
        `/admin/invoices/${String(input.id)}/issue`,
        { qualified: input.qualified, due_at: input.due_at },
      )
      return toInvoiceWithLines(dto)
    },
    onSuccess: (invoice) => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.detail(invoice.id) })
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.lists() })
    },
  })
}

export interface DownloadTokenResult {
  url: string
  expires_at: string
}

/** POST /admin/invoices/{id}/download-token — generates a public PDF download link. */
export function useGenerateDownloadToken(): UseMutationResult<
  DownloadTokenResult,
  AppError,
  number
> {
  return useMutation<DownloadTokenResult, AppError, number>({
    mutationFn: (id) =>
      apiClient.post<DownloadTokenResult>(`/admin/invoices/${String(id)}/download-token`),
  })
}

/**
 * Preview returned instead of a real send for demo organizations (#626): the
 * API answers 200 with the message it would have sent (never delivering it,
 * because demo clients use undeliverable `.example` addresses).
 */
export interface SendInvoiceEmailPreview {
  preview: true
  recipient: string
  subject: string
  body_html: string
}

/**
 * POST /admin/invoices/{id}/send-email — sends the invoice PDF to the client.
 *
 * Non-demo orgs deliver for real and answer 204 (resolves to `null` here).
 * Demo orgs do not send and answer 200 with a {@link SendInvoiceEmailPreview}
 * so the UI can show what would have gone out (#626).
 */
export function useSendInvoiceEmail(): UseMutationResult<
  SendInvoiceEmailPreview | null,
  AppError,
  number
> {
  return useMutation<SendInvoiceEmailPreview | null, AppError, number>({
    mutationFn: async (id) => {
      const result = await apiClient.post<SendInvoiceEmailPreview | undefined>(
        `/admin/invoices/${String(id)}/send-email`,
      )
      return result ?? null
    },
  })
}
