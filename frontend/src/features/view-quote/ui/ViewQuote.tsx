import { Link, useNavigate } from 'react-router-dom'
import { toClientId, useClient } from '@/entities/client'
import { useCompanySettings } from '@/entities/company-settings'
import {
  quoteStatusTone,
  useDownloadQuotePdf,
  type CreateQuoteInput,
  type QuoteId,
} from '@/entities/quote'
import { useTranslation } from '@/shared/i18n'
import { formatCalendarDate, formatJstDate } from '@/shared/lib/format-date'
import { formatYen } from '@/shared/lib/format-money'
import {
  Badge,
  Button,
  ErrorState,
  InlineAlert,
  LineItemsTable,
  LoadingState,
  Stack,
} from '@/shared/ui'
import { useViewQuote, type ViewQuoteState } from '../hooks/use-view-quote'

export interface ViewQuoteProps {
  quoteId: QuoteId
}

type QuoteReady = Extract<ViewQuoteState, { kind: 'ready' }>

/** Quote detail. Loads the resource, then renders it as a 御見積書 document. */
export function ViewQuote({ quoteId }: ViewQuoteProps) {
  const { t } = useTranslation()
  const state = useViewQuote(quoteId)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.quotes.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.quotes.detail.error')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  return <QuoteDocument quoteId={quoteId} state={state} />
}

/**
 * Renders a loaded quote as the spec document layout (`.doc`): header + status
 * badge and lifecycle actions, an optional status alert, then the `.doc` card
 * (宛先/発行元 parties → 明細 → totals). Party data is fetched here — the detail
 * API omits the client name (list-only) and the issuer profile.
 */
function QuoteDocument({ quoteId, state }: { quoteId: QuoteId; state: QuoteReady }) {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const quote = state.quote
  const busy = state.isStatusPending || state.isConverting
  const pdf = useDownloadQuotePdf(quoteId, quote.quote_number)
  const client = useClient(toClientId(quote.client_id))
  const company = useCompanySettings()

  // Duplicate (#316): seed a fresh create form with this quote's client, notes,
  // and lines. valid_until is not copied (falls back to the issuer default).
  const duplicate = (): void => {
    const snapshot: CreateQuoteInput = {
      client_id: quote.client_id,
      valid_until: null,
      notes: quote.notes,
      line_items: quote.line_items.map((line) => ({
        description: line.description,
        quantity: line.quantity,
        unit_price_cents: line.unit_price_cents,
        tax_rate_bps: line.tax_rate_bps,
      })),
    }
    void navigate('/quotes/new', { state: { duplicate: snapshot } })
  }

  const clientName = client.data?.name ?? null
  const issuer = company.data

  return (
    <Stack gap="md">
      <Link to="/quotes" className="nav-back">
        ← {t('admin.quotes.detail.backToList')}
      </Link>

      <div className="page-head" style={{ alignItems: 'center' }}>
        <div className="row-gap">
          <h1 className="page-title">{quote.quote_number}</h1>
          <Badge tone={quoteStatusTone[quote.status]} className="badge-status">
            {t(`admin.quotes.status.${quote.status}`)}
          </Badge>
        </div>
        <div className="actions">
          <Button onClick={pdf.download} disabled={pdf.isDownloading}>
            {pdf.isDownloading
              ? t('admin.quotes.detail.downloadingPdf')
              : t('admin.quotes.detail.downloadPdf')}
          </Button>
          <Button variant="ghost" onClick={duplicate}>
            {t('admin.quotes.detail.duplicate')}
          </Button>
          {state.canSend && (
            <Button
              onClick={() => {
                state.changeStatus('sent')
              }}
              disabled={busy}
            >
              {t('admin.quotes.action.send')}
            </Button>
          )}
          {state.canAccept && (
            <Button
              onClick={() => {
                state.changeStatus('accepted')
              }}
              disabled={busy}
            >
              {t('admin.quotes.action.accept')}
            </Button>
          )}
          {state.canReject && (
            <Button
              onClick={() => {
                state.changeStatus('rejected')
              }}
              disabled={busy}
            >
              {t('admin.quotes.action.reject')}
            </Button>
          )}
          {state.canExpire && (
            <Button
              onClick={() => {
                state.changeStatus('expired')
              }}
              disabled={busy}
            >
              {t('admin.quotes.action.expire')}
            </Button>
          )}
          {state.canConvert && (
            <Button onClick={state.convertToInvoice} disabled={busy}>
              {state.isConverting
                ? t('admin.quotes.detail.converting')
                : t('admin.quotes.detail.convertToInvoice')}
            </Button>
          )}
        </div>
      </div>

      {/* An accepted quote can be converted — surface that as a success note. */}
      {quote.status === 'accepted' && (
        <InlineAlert tone="success" message={t('admin.quotes.detail.acceptedNote')} />
      )}

      {/* Feedback sits outside the button row (Issue #266). */}
      {pdf.errorMessage !== null && (
        <div className="ar-banner">
          <InlineAlert tone="error" message={pdf.errorMessage} />
        </div>
      )}
      {state.actionError !== null && (
        <div className="ar-banner">
          <InlineAlert tone="error" message={state.actionError} />
        </div>
      )}

      <div className="doc">
        <div className="doc-head">
          <div>
            <div className="doc-title">{t('admin.quotes.detail.docTitle')}</div>
            <div className="doc-id-num">{quote.quote_number}</div>
          </div>
          <div className="doc-meta">
            {quote.issued_at !== null && (
              <div>
                {t('admin.quotes.detail.issuedAt')} <b>{formatJstDate(quote.issued_at)}</b>
              </div>
            )}
            {quote.valid_until !== null && (
              <div>
                {t('admin.quotes.detail.validUntil')} <b>{formatCalendarDate(quote.valid_until)}</b>
              </div>
            )}
          </div>
        </div>

        <div className="doc-parties">
          <div>
            <div className="party-label">{t('admin.quotes.detail.recipient')}</div>
            {clientName !== null && (
              <div className="party-name">
                {t('admin.quotes.detail.corpHonorific', { name: clientName })}
              </div>
            )}
            {client.data?.contact_name != null && (
              <div className="party-line">
                {t('admin.quotes.detail.personHonorific', { name: client.data.contact_name })}
              </div>
            )}
          </div>
          {issuer != null && (
            <div>
              <div className="party-label">{t('admin.quotes.detail.issuer')}</div>
              <div className="party-name">{issuer.legal_name}</div>
              {issuer.registration_number !== null && (
                <div className="party-line num">
                  {t('admin.quotes.detail.registrationNumber', {
                    number: issuer.registration_number,
                  })}
                </div>
              )}
            </div>
          )}
        </div>

        <div className="doc-body">
          <div style={{ marginBottom: '1rem' }}>
            <LineItemsTable items={quote.line_items} />
          </div>
          <div className="totals">
            <div className="totals-row">
              <span className="t-label">{t('admin.quotes.detail.subtotal')}</span>
              <span className="t-val">{formatYen(quote.subtotal_cents)}</span>
            </div>
            <div className="totals-row">
              <span className="t-label">{t('admin.quotes.detail.tax')}</span>
              <span className="t-val">{formatYen(quote.tax_cents)}</span>
            </div>
            <div className="totals-row grand">
              <span className="t-label">{t('admin.quotes.detail.total')}</span>
              <span className="t-val">{formatYen(quote.total_cents)}</span>
            </div>
          </div>
        </div>
      </div>
    </Stack>
  )
}
