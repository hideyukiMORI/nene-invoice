import { Link, useNavigate } from 'react-router-dom'
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
  Text,
  TotalRow,
} from '@/shared/ui'
import { useViewQuote } from '../hooks/use-view-quote'

export interface ViewQuoteProps {
  quoteId: QuoteId
}

export function ViewQuote({ quoteId }: ViewQuoteProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const state = useViewQuote(quoteId)
  const quoteNumber = state.kind === 'ready' ? state.quote.quote_number : ''
  const pdf = useDownloadQuotePdf(quoteId, quoteNumber)

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

  const quote = state.quote
  const busy = state.isStatusPending || state.isConverting

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

  return (
    <Stack gap="lg">
      <Stack gap="sm">
        <Link to="/quotes" className="text-body text-accent">
          ← {t('admin.quotes.detail.backToList')}
        </Link>
        <div className="flex flex-wrap items-start justify-between gap-stack-sm">
          <Text as="h1" variant="heading-md" className="num">
            {quote.quote_number}
          </Text>
          <Stack direction="row" gap="sm" className="flex-wrap justify-end">
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
          </Stack>
        </div>

        {/* Feedback sits outside the button row (Issue #266). */}
        {pdf.errorMessage !== null && (
          <div className="ar-banner">
            <InlineAlert tone="error" message={pdf.errorMessage} />
          </div>
        )}

        <Stack direction="row" gap="md">
          <Badge tone={quoteStatusTone[quote.status]} className="badge-status">
            {t(`admin.quotes.status.${quote.status}`)}
          </Badge>
          {quote.issued_at !== null && (
            <Text variant="muted">
              {t('admin.quotes.detail.issuedAt')}: {formatJstDate(quote.issued_at)}
            </Text>
          )}
          {quote.valid_until !== null && (
            <Text variant="muted">
              {t('admin.quotes.detail.validUntil')}: {formatCalendarDate(quote.valid_until)}
            </Text>
          )}
        </Stack>
        {state.actionError !== null && (
          <div className="ar-banner">
            <InlineAlert tone="error" message={state.actionError} />
          </div>
        )}
      </Stack>

      <LineItemsTable items={quote.line_items} />

      <Stack gap="sm" className="ml-auto w-64">
        <TotalRow
          label={t('admin.quotes.detail.subtotal')}
          value={formatYen(quote.subtotal_cents)}
        />
        <TotalRow label={t('admin.quotes.detail.tax')} value={formatYen(quote.tax_cents)} />
        <TotalRow label={t('admin.quotes.detail.total')} value={formatYen(quote.total_cents)} />
      </Stack>
    </Stack>
  )
}
