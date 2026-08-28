import { useCompanySettings } from '@/entities/company-settings'
import {
  useInvoice,
  useIssueInvoice as useIssueInvoiceMutation,
  type InvoiceId,
} from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { useToast } from '@/shared/ui'

export interface UseIssueInvoice {
  /** Only draft invoices can be issued; the action hides otherwise. */
  canIssue: boolean
  /**
   * Whether this invoice will be issued as a 適格請求書.
   *
   * `null` while the company settings are still unresolved — the caller must
   * not offer the action yet. See the note on `qualifiedFrom` below for why
   * this is three-valued rather than defaulting to `false`.
   */
  qualified: boolean | null
  issue: () => void
  isPending: boolean
  errorMessage: string | null
}

/**
 * Decides whether the issuer can hand out a 適格請求書 at all (#771).
 *
 * 🔴 The registration number is the ONLY input. `accounting-compliance.md` §4:
 * "An invoice MUST NOT be markable as qualified while the issuer registration
 * number is empty." Before #771 the UI sent `qualified: true` unconditionally,
 * so an issuer without a T-number could not issue anything — the backend
 * rejected the only request the UI knew how to make (422
 * `QualifiedInvoiceIncompleteException`). The API already accepted
 * `qualified: false`; nothing but the UI was closed.
 *
 * 🔴 There is exactly ONE representation of "no registration number" in the
 * domain, and it is `null`. Both boundaries collapse the empty string before a
 * value can reach here — `RequestField::optionalString` on the way in
 * (`''` → `null`) and `PdoCompanySettingsRepository::nullableString` on the way
 * out. So `''` cannot arrive from the API today. The check below still names it
 * to keep the decision readable at the point it is made: an issuer's
 * eligibility must not depend on remembering that two layers away.
 *
 * 🔴 Three-valued on purpose. `false` while loading would be indistinguishable
 * from "measured, and this issuer has no number" — and it is the difference
 * between a document that claims to be a 適格請求書 and one that does not. An
 * unresolved query is not a measurement of anything.
 */
function qualifiedFrom(registrationNumber: string | null | undefined): boolean {
  return (
    registrationNumber !== null && registrationNumber !== undefined && registrationNumber !== ''
  )
}

/**
 * Issues the invoice, as a 適格請求書 only when the issuer has a registration
 * number. Reads the (cached) invoice to gate the action on draft status —
 * shares the detail query, so no extra fetch. The company settings are already
 * on the detail screen for the bank block, so that is cached too.
 */
export function useIssueInvoice(invoiceId: InvoiceId): UseIssueInvoice {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const invoice = useInvoice(invoiceId)
  const company = useCompanySettings()
  const mutation = useIssueInvoiceMutation()

  // `data` is `CompanySettings | null` — null is a settled answer (no settings
  // row yet), which means no registration number. Only an unsettled query is
  // unknown.
  const qualified = company.isSuccess ? qualifiedFrom(company.data?.registration_number) : null

  return {
    canIssue: invoice.data?.status === 'draft',
    qualified,
    issue: () => {
      // Guard rather than assert: `issue` is a callback the UI could fire from a
      // stale render. Issuing is irreversible (it allocates the number), so an
      // unknown eligibility must not resolve to a guess.
      if (qualified === null) {
        return
      }

      mutation.mutate(
        { id: invoiceId, qualified, due_at: null },
        {
          onSuccess: (issued) => {
            showToast({
              tone: 'ok',
              title: t('admin.invoices.issue.successTitle'),
              description:
                issued.invoice_number !== null
                  ? t('admin.invoices.issue.successBody', { number: issued.invoice_number })
                  : t('admin.invoices.issue.successBodyNoNumber'),
            })
          },
        },
      )
    },
    isPending: mutation.isPending,
    errorMessage: mutation.isError
      ? t(
          qualified === false
            ? 'admin.invoices.issue.errorUnqualified'
            : 'admin.invoices.issue.error',
        )
      : null,
  }
}
