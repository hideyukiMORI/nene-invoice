import { Link } from 'react-router-dom'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import { Button, EmptyState, ErrorState, Spinner, Stack, Text } from '@/shared/ui'
import { useListQuotes } from '../hooks/use-list-quotes'

export function ListQuotes() {
  const { t } = useTranslation()
  const state = useListQuotes()

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.quotes.title')}
        </Text>
        <Link to="/quotes/new" className="text-body text-accent">
          {t('admin.quotes.newButton')}
        </Link>
      </div>

      {state.kind === 'loading' && (
        <Stack direction="row" gap="sm">
          <Spinner label={t('admin.quotes.loading')} />
          <Text variant="muted">{t('admin.quotes.loading')}</Text>
        </Stack>
      )}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.quotes.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.quotes.empty')} />}

      {state.kind === 'ready' && (
        <>
          <table className="w-full border-collapse text-body">
            <thead>
              <tr className="border-b border-border text-left">
                <th className="py-stack-sm pr-inline-md font-medium">
                  {t('admin.quotes.col.number')}
                </th>
                <th className="py-stack-sm pr-inline-md font-medium">
                  {t('admin.quotes.col.status')}
                </th>
                <th className="py-stack-sm pr-inline-md font-medium">
                  {t('admin.quotes.col.client')}
                </th>
                <th className="py-stack-sm text-right font-medium">
                  {t('admin.quotes.col.total')}
                </th>
              </tr>
            </thead>
            <tbody>
              {state.quotes.map((quote) => (
                <tr key={quote.id} className="border-b border-border">
                  <td className="py-stack-sm pr-inline-md">
                    <Link to={`/quotes/${String(quote.id)}`} className="text-accent">
                      {quote.quote_number}
                    </Link>
                  </td>
                  <td className="py-stack-sm pr-inline-md">
                    {t(`admin.quotes.status.${quote.status}`)}
                  </td>
                  <td className="py-stack-sm pr-inline-md">{quote.client_id}</td>
                  <td className="py-stack-sm text-right">{formatYen(quote.total_cents)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {state.pagination.totalPages > 1 && (
            <div className="flex items-center justify-between">
              <Button onClick={state.pagination.prevPage} disabled={!state.pagination.hasPrev}>
                {t('admin.invoices.pagination.prev')}
              </Button>
              <Text variant="muted">
                {t('admin.invoices.pagination.info', {
                  page: state.pagination.page,
                  total: state.pagination.totalPages,
                })}
              </Text>
              <Button onClick={state.pagination.nextPage} disabled={!state.pagination.hasNext}>
                {t('admin.invoices.pagination.next')}
              </Button>
            </div>
          )}
        </>
      )}
    </Stack>
  )
}
