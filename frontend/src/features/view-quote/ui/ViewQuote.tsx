import { Link } from 'react-router-dom'
import type { QuoteId } from '@/entities/quote'
import { useTranslation } from '@/shared/i18n'
import { formatTaxRate, formatYen } from '@/shared/lib/format-money'
import { Button, ErrorState, Spinner, Stack, Text } from '@/shared/ui'
import { useViewQuote } from '../hooks/use-view-quote'

export interface ViewQuoteProps {
  quoteId: QuoteId
}

export function ViewQuote({ quoteId }: ViewQuoteProps) {
  const { t } = useTranslation()
  const state = useViewQuote(quoteId)

  if (state.kind === 'loading') {
    return (
      <Stack direction="row" gap="sm">
        <Spinner label={t('admin.quotes.loading')} />
        <Text variant="muted">{t('admin.quotes.loading')}</Text>
      </Stack>
    )
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
        {state.actionError !== null && (
          <Text variant="muted" role="alert">
            {state.actionError}
          </Text>
        )}
      </Stack>

      <table className="w-full border-collapse text-body">
        <thead>
          <tr className="border-b border-border text-left">
            <th className="py-stack-sm pr-inline-md font-medium">
              {t('admin.invoices.line.description')}
            </th>
            <th className="py-stack-sm pr-inline-md text-right font-medium">
              {t('admin.invoices.line.quantity')}
            </th>
            <th className="py-stack-sm pr-inline-md text-right font-medium">
              {t('admin.invoices.line.unitPrice')}
            </th>
            <th className="py-stack-sm pr-inline-md text-right font-medium">
              {t('admin.invoices.line.taxRate')}
            </th>
            <th className="py-stack-sm text-right font-medium">
              {t('admin.invoices.line.lineSubtotal')}
            </th>
          </tr>
        </thead>
        <tbody>
          {quote.line_items.map((line, index) => (
            <tr key={index} className="border-b border-border">
              <td className="py-stack-sm pr-inline-md">{line.description}</td>
              <td className="py-stack-sm pr-inline-md text-right">{line.quantity}</td>
              <td className="py-stack-sm pr-inline-md text-right">
                {formatYen(line.unit_price_cents)}
              </td>
              <td className="py-stack-sm pr-inline-md text-right">
                {formatTaxRate(line.tax_rate_bps)}
              </td>
              <td className="py-stack-sm text-right">{formatYen(line.line_subtotal_cents)}</td>
            </tr>
          ))}
        </tbody>
      </table>

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

function TotalRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between">
      <Text variant="muted">{label}</Text>
      <Text>{value}</Text>
    </div>
  )
}
