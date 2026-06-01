import { Link } from 'react-router-dom'
import { quoteStatusTone } from '@/entities/quote'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import { Badge, Button, EmptyState, ErrorState, LoadingState, Stack, Text } from '@/shared/ui'
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

      {state.kind === 'loading' && <LoadingState message={t('admin.quotes.loading')} />}

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
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th>{t('admin.quotes.col.number')}</th>
                  <th>{t('admin.quotes.col.status')}</th>
                  <th>{t('admin.quotes.col.client')}</th>
                  <th className="tr">{t('admin.quotes.col.total')}</th>
                </tr>
              </thead>
              <tbody>
                {state.quotes.map((quote) => (
                  <tr key={quote.id}>
                    <td data-label={t('admin.quotes.col.number')}>
                      <Link to={`/quotes/${String(quote.id)}`} className="num text-accent">
                        {quote.quote_number}
                      </Link>
                    </td>
                    <td data-label={t('admin.quotes.col.status')}>
                      <Badge tone={quoteStatusTone[quote.status]}>
                        {t(`admin.quotes.status.${quote.status}`)}
                      </Badge>
                    </td>
                    <td className="num" data-label={t('admin.quotes.col.client')}>
                      {quote.client_id}
                    </td>
                    <td className="tr num" data-label={t('admin.quotes.col.total')}>
                      {formatYen(quote.total_cents)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {state.pagination.totalPages > 1 && (
            <div className="flex items-center justify-between">
              <Button onClick={state.pagination.prevPage} disabled={!state.pagination.hasPrev}>
                {t('common.pagination.prev')}
              </Button>
              <Text variant="muted">
                {t('common.pagination.info', {
                  page: state.pagination.page,
                  total: state.pagination.totalPages,
                })}
              </Text>
              <Button onClick={state.pagination.nextPage} disabled={!state.pagination.hasNext}>
                {t('common.pagination.next')}
              </Button>
            </div>
          )}
        </>
      )}
    </Stack>
  )
}
