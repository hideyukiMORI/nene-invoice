import { Link } from 'react-router-dom'
import { useDownloadQuotePdf, type QuoteId } from '@/entities/quote'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import {
  Button,
  ErrorState,
  LineItemsTable,
  LoadingState,
  MutationError,
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

  return (
    <Stack gap="lg">
      <Stack gap="sm">
        <Link to="/quotes" className="text-body text-accent">
          ← {t('admin.quotes.detail.backToList')}
        </Link>
        <div className="flex items-start justify-between">
          <Text as="h1" variant="heading-md">
            {quote.quote_number}
          </Text>
          <Stack direction="row" gap="sm">
            <Stack gap="sm">
              <Button onClick={pdf.download} disabled={pdf.isDownloading}>
                {pdf.isDownloading
                  ? t('admin.quotes.detail.downloadingPdf')
                  : t('admin.quotes.detail.downloadPdf')}
              </Button>
              <MutationError message={pdf.errorMessage} />
            </Stack>
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
        <Stack direction="row" gap="md">
          <Text variant="muted">{t(`admin.quotes.status.${quote.status}`)}</Text>
          {quote.issued_at !== null && (
            <Text variant="muted">
              {t('admin.quotes.detail.issuedAt')}: {quote.issued_at.slice(0, 10)}
            </Text>
          )}
          {quote.valid_until !== null && (
            <Text variant="muted">
              {t('admin.quotes.detail.validUntil')}: {quote.valid_until.slice(0, 10)}
            </Text>
          )}
        </Stack>
        <MutationError message={state.actionError} />
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
