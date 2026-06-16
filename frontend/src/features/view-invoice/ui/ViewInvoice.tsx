import { Link, useNavigate } from 'react-router-dom'
import { toClientId, useClient } from '@/entities/client'
import { useCompanySettings } from '@/entities/company-settings'
import {
  invoiceStatusTone,
  useDownloadInvoicePdf,
  useSendInvoiceEmail,
  type CreateInvoiceInput,
  type InvoiceId,
  type InvoiceWithLines,
} from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatCalendarDate, formatJstDate } from '@/shared/lib/format-date'
import { formatYen } from '@/shared/lib/format-money'
import {
  ActionError,
  Badge,
  Button,
  ErrorState,
  InlineAlert,
  LineItemsTable,
  LoadingState,
  Stack,
  Text,
  useToast,
} from '@/shared/ui'
import { useGenerateDownloadLink } from '../hooks/use-generate-download-link'
import { useViewInvoice } from '../hooks/use-view-invoice'

export interface ViewInvoiceProps {
  invoiceId: InvoiceId
}

/** Invoice detail. Loads the resource, then renders it as a 請求書 document. */
export function ViewInvoice({ invoiceId }: ViewInvoiceProps) {
  const { t } = useTranslation()
  const state = useViewInvoice(invoiceId)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.invoices.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.invoices.detail.error')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  return <InvoiceDocument invoiceId={invoiceId} invoice={state.invoice} />
}

/**
 * Renders a loaded invoice as the spec document layout (`.doc`): header +
 * status badges and actions, an optional status alert, then the `.doc` card
 * (発行元/宛先 parties → 明細 → totals). Party data is fetched here — the detail
 * API omits the client name (list-only) and the issuer's bank info.
 */
function InvoiceDocument({
  invoiceId,
  invoice,
}: {
  invoiceId: InvoiceId
  invoice: InvoiceWithLines
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const isIssued = invoice.status !== 'draft'
  const pdf = useDownloadInvoicePdf(invoiceId, invoice.invoice_number)
  const sendEmail = useSendInvoiceEmail()
  const link = useGenerateDownloadLink(invoiceId, isIssued)
  const client = useClient(toClientId(invoice.client_id))
  const company = useCompanySettings()

  // Duplicate (#316): seed a fresh create form with this invoice's client,
  // notes, and lines (always available regardless of status).
  const duplicate = (): void => {
    const snapshot: CreateInvoiceInput = {
      client_id: invoice.client_id,
      notes: invoice.notes,
      line_items: invoice.line_items.map((line) => ({
        description: line.description,
        quantity: line.quantity,
        unit_price_cents: line.unit_price_cents,
        tax_rate_bps: line.tax_rate_bps,
      })),
    }
    void navigate('/invoices/new', { state: { duplicate: snapshot } })
  }

  // 型3 success toast on send; reused by the 型2 "resend" recovery action.
  const sendInvoiceEmail = () => {
    sendEmail.mutate(invoiceId, {
      onSuccess: () => {
        showToast({
          tone: 'ok',
          title: t('admin.invoices.detail.emailSentTitle'),
          description:
            client.data != null
              ? t('admin.invoices.detail.emailSentBody', { client: client.data.name })
              : t('admin.invoices.detail.emailSentBodyNoClient'),
        })
      },
    })
  }

  const clientName = client.data?.name ?? null
  const bank = company.data
  const hasBank = bank != null && bank.bank_name !== null
  const outstanding = invoice.outstanding_cents
  const showOutstanding = outstanding !== null && outstanding > 0

  return (
    <Stack gap="md">
      <Link to="/invoices" className="nav-back">
        ← {t('admin.invoices.detail.backToList')}
      </Link>

      <div className="page-head" style={{ alignItems: 'center' }}>
        <div className="row-gap">
          <h1 className="page-title">
            {invoice.invoice_number ?? t('admin.invoices.detail.notIssued')}
          </h1>
          <Badge tone={invoiceStatusTone[invoice.status]} className="badge-status">
            {t(`admin.invoices.status.${invoice.status}`)}
          </Badge>
          {invoice.is_overdue && <Badge tone="danger">{t('admin.invoices.status.overdue')}</Badge>}
          {invoice.is_qualified_invoice && (
            <Badge tone="brand">{t('admin.invoices.detail.qualified')}</Badge>
          )}
        </div>
        <div className="actions">
          <Button variant="ghost" onClick={duplicate}>
            {t('admin.invoices.detail.duplicate')}
          </Button>
          {pdf.canDownload && (
            <Button onClick={pdf.download} disabled={pdf.isDownloading}>
              {pdf.isDownloading
                ? t('admin.invoices.detail.downloadingPdf')
                : t('admin.invoices.detail.downloadPdf')}
            </Button>
          )}
          {isIssued && (
            <Button onClick={sendInvoiceEmail} disabled={sendEmail.isPending}>
              {sendEmail.isPending
                ? t('admin.invoices.detail.sendingEmail')
                : t('admin.invoices.detail.sendEmail')}
            </Button>
          )}
          {link.canGenerate && (
            <Button onClick={link.generate} disabled={link.isGenerating}>
              {link.isGenerating
                ? t('admin.invoices.detail.generatingLink')
                : t('admin.invoices.detail.generateLink')}
            </Button>
          )}
        </div>
      </div>

      {/* Status alert: overdue (型2-ish notice) or a partial-payment note. */}
      {invoice.is_overdue && showOutstanding ? (
        <InlineAlert
          tone="error"
          message={t('admin.invoices.detail.overdueNote', {
            dueAt: invoice.due_at !== null ? formatCalendarDate(invoice.due_at) : '—',
            amount: formatYen(outstanding),
          })}
        />
      ) : invoice.status === 'partially_paid' && showOutstanding ? (
        <InlineAlert
          tone="info"
          message={t('admin.invoices.detail.partialNote', { amount: formatYen(outstanding) })}
        />
      ) : null}

      {/* Feedback sits outside the button row so a failing action never
          stretches one button's column (Issue #266). */}
      {pdf.errorMessage !== null && (
        <div className="ar-banner">
          <InlineAlert tone="error" message={pdf.errorMessage} />
        </div>
      )}
      {sendEmail.isError && (
        <div className="ar-banner">
          <ActionError
            title={t('admin.invoices.detail.emailErrorTitle')}
            description={t('admin.invoices.detail.emailErrorBody')}
            actions={[
              {
                label: t('admin.invoices.detail.emailRetry'),
                variant: 'solid',
                onClick: sendInvoiceEmail,
              },
              {
                label: t('admin.invoices.detail.emailCheckClient'),
                variant: 'outline',
                onClick: () => {
                  void navigate(`/clients/${String(invoice.client_id)}/edit`)
                },
              },
            ]}
            onClose={() => {
              sendEmail.reset()
            }}
            closeLabel={t('common.actions.close')}
          />
        </div>
      )}
      {link.downloadUrl !== null && (
        <Stack gap="sm" className="ar-banner">
          <Text variant="muted" className="break-all text-caption">
            {`${window.location.origin}${link.downloadUrl}`}
          </Text>
          <Stack direction="row" gap="sm">
            <Button onClick={link.copy}>
              {link.copied
                ? t('admin.invoices.detail.linkCopied')
                : t('admin.invoices.detail.linkCopy')}
            </Button>
            {link.expiresAt !== null && (
              <Text variant="muted">
                {t('admin.invoices.detail.linkExpiry', { expiresAt: link.expiresAt })}
              </Text>
            )}
          </Stack>
        </Stack>
      )}
      {link.errorMessage !== null && (
        <div className="ar-banner">
          <InlineAlert tone="error" message={link.errorMessage} />
        </div>
      )}

      <div className="doc">
        <div className="doc-head">
          <div>
            <div className="doc-title">{t('admin.nav.invoices')}</div>
            <div className="doc-id-num">
              {invoice.invoice_number ?? t('admin.invoices.detail.notIssued')}
            </div>
          </div>
          <div className="doc-meta">
            {invoice.issued_at !== null && (
              <div>
                {t('admin.invoices.detail.issuedAt')} <b>{formatJstDate(invoice.issued_at)}</b>
              </div>
            )}
            {invoice.due_at !== null && (
              <div>
                {t('admin.invoices.detail.dueAt')} <b>{formatCalendarDate(invoice.due_at)}</b>
              </div>
            )}
          </div>
        </div>

        <div className="doc-parties">
          <div>
            <div className="party-label">{t('admin.invoices.detail.billTo')}</div>
            {clientName !== null && (
              <div className="party-name">
                {t('admin.invoices.detail.corpHonorific', { name: clientName })}
              </div>
            )}
            {client.data?.contact_name != null && (
              <div className="party-line">
                {t('admin.invoices.detail.personHonorific', { name: client.data.contact_name })}
              </div>
            )}
            {client.data?.billing_address != null && (
              <div className="party-line">{client.data.billing_address}</div>
            )}
          </div>
          {hasBank && (
            <div>
              <div className="party-label">{t('admin.invoices.detail.payTo')}</div>
              <div className="party-name">
                {bank.bank_name}
                {bank.bank_branch !== null ? ` ${bank.bank_branch}支店` : ''}
              </div>
              {(bank.account_type !== null || bank.account_number !== null) && (
                <div className="party-line num">
                  {[bank.account_type, bank.account_number].filter(Boolean).join(' ')}
                </div>
              )}
            </div>
          )}
        </div>

        <div className="doc-body">
          <div style={{ marginBottom: '1rem' }}>
            <LineItemsTable items={invoice.line_items} />
          </div>
          <div className="totals">
            <div className="totals-row">
              <span className="t-label">{t('admin.invoices.detail.subtotal')}</span>
              <span className="t-val">{formatYen(invoice.subtotal_cents)}</span>
            </div>
            <div className="totals-row">
              <span className="t-label">{t('admin.invoices.detail.tax')}</span>
              <span className="t-val">{formatYen(invoice.tax_cents)}</span>
            </div>
            <div className="totals-row grand">
              <span className="t-label">{t('admin.invoices.detail.total')}</span>
              <span className="t-val">{formatYen(invoice.total_cents)}</span>
            </div>
            {outstanding !== null && (
              <div className={`totals-row balance${invoice.is_overdue ? ' over' : ''}`}>
                <span className="t-label">{t('admin.invoices.detail.outstanding')}</span>
                <span className="t-val">{formatYen(outstanding)}</span>
              </div>
            )}
          </div>
        </div>
      </div>
    </Stack>
  )
}
