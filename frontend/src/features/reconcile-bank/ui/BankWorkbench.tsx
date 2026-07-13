import { useState } from 'react'
import {
  BANK_TRANSACTION_STATUSES,
  bankTransactionStatusTone,
  useBankTransactionSuggestions,
  useConfirmBankMatch,
  useIgnoreBankTransaction,
  type BankTransaction,
  type BankTransactionId,
  type BankTransactionStatus,
} from '@/entities/bank-transaction'
import { useTranslation } from '@/shared/i18n'
import { formatCalendarDate } from '@/shared/lib/format-date'
import { formatYen } from '@/shared/lib/format-money'
import {
  Badge,
  Button,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  Field,
  LoadingState,
  Select,
  Stack,
  Text,
  useToast,
} from '@/shared/ui'
import { useBankWorkbench } from '../hooks/use-bank-workbench'

/** Staged bank lines with a status filter, per-row suggestions, confirm and ignore. */
export function BankWorkbench() {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const view = useBankWorkbench()
  const ignore = useIgnoreBankTransaction()

  const [suggestFor, setSuggestFor] = useState<BankTransaction | null>(null)
  const [ignoreFor, setIgnoreFor] = useState<BankTransaction | null>(null)

  const confirmIgnore = (): void => {
    if (ignoreFor === null) return
    ignore.mutate(ignoreFor.id, {
      onSuccess: () => {
        setIgnoreFor(null)
        showToast({ tone: 'ok', title: t('admin.bankReconciliation.ignore.done') })
      },
      onError: () => {
        showToast({ tone: 'err', title: t('admin.bankReconciliation.ignore.error') })
      },
    })
  }

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h2" variant="heading-sm">
          {t('admin.bankReconciliation.workbench.title')}
        </Text>
        <Field id="bank-status" label={t('admin.bankReconciliation.filter.status')}>
          <Select
            id="bank-status"
            value={view.status ?? ''}
            onChange={(e) => {
              const v = e.target.value
              view.setStatus(v === '' ? null : (v as BankTransactionStatus))
            }}
          >
            <option value="">{t('admin.bankReconciliation.filter.statusAny')}</option>
            {BANK_TRANSACTION_STATUSES.map((s) => (
              <option key={s} value={s}>
                {t(`admin.bankReconciliation.status.${s}`)}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      {view.state.kind === 'loading' && (
        <LoadingState message={t('admin.bankReconciliation.loading')} />
      )}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.bankReconciliation.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.bankReconciliation.empty')} />}

      {view.state.kind === 'ready' && (
        <>
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th>{t('admin.bankReconciliation.col.date')}</th>
                  <th>{t('admin.bankReconciliation.col.direction')}</th>
                  <th>{t('admin.bankReconciliation.col.payer')}</th>
                  <th className="tr">{t('admin.bankReconciliation.col.amount')}</th>
                  <th>{t('admin.bankReconciliation.col.status')}</th>
                  <th className="tr">{t('admin.bankReconciliation.col.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {view.state.transactions.map((tx) => (
                  <tr key={tx.id}>
                    <td className="num" data-label={t('admin.bankReconciliation.col.date')}>
                      {formatCalendarDate(tx.value_date)}
                    </td>
                    <td data-label={t('admin.bankReconciliation.col.direction')}>
                      <Badge tone={tx.direction === 'credit' ? 'ok' : 'neutral'}>
                        {t(`admin.bankReconciliation.direction.${tx.direction}`)}
                      </Badge>
                    </td>
                    <td data-label={t('admin.bankReconciliation.col.payer')}>
                      {tx.payer_name ?? tx.description ?? '—'}
                    </td>
                    <td className="tr num" data-label={t('admin.bankReconciliation.col.amount')}>
                      {formatYen(tx.amount_cents)}
                    </td>
                    <td data-label={t('admin.bankReconciliation.col.status')}>
                      <Badge tone={bankTransactionStatusTone[tx.status]}>
                        {t(`admin.bankReconciliation.status.${tx.status}`)}
                      </Badge>
                    </td>
                    <td className="tr" data-label={t('admin.bankReconciliation.col.actions')}>
                      <Stack direction="row" gap="sm" className="justify-end">
                        {tx.status === 'unmatched' && tx.direction === 'credit' && (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                              setSuggestFor(tx)
                            }}
                          >
                            {t('admin.bankReconciliation.actions.suggest')}
                          </Button>
                        )}
                        {tx.status === 'unmatched' && (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                              setIgnoreFor(tx)
                            }}
                          >
                            {t('admin.bankReconciliation.actions.ignore')}
                          </Button>
                        )}
                      </Stack>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {view.pagination.totalPages > 1 && (
            <div className="flex items-center justify-between">
              <Button onClick={view.pagination.prevPage} disabled={!view.pagination.hasPrev}>
                {t('common.pagination.prev')}
              </Button>
              <Text variant="muted">
                {t('common.pagination.info', {
                  page: view.pagination.page,
                  total: view.pagination.totalPages,
                })}
              </Text>
              <Button onClick={view.pagination.nextPage} disabled={!view.pagination.hasNext}>
                {t('common.pagination.next')}
              </Button>
            </div>
          )}
        </>
      )}

      {suggestFor !== null && (
        <SuggestionsDialog
          transaction={suggestFor}
          onClose={() => {
            setSuggestFor(null)
          }}
        />
      )}

      {ignoreFor !== null && (
        <ConfirmDialog
          title={t('admin.bankReconciliation.ignore.title')}
          message={t('admin.bankReconciliation.ignore.message', {
            payer: ignoreFor.payer_name ?? ignoreFor.description ?? '—',
            amount: formatYen(ignoreFor.amount_cents),
          })}
          confirmLabel={t('admin.bankReconciliation.ignore.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={ignore.isPending}
          onConfirm={confirmIgnore}
          onCancel={() => {
            setIgnoreFor(null)
          }}
        />
      )}
    </Stack>
  )
}

/**
 * Modal listing scored invoice candidates for a staged deposit. Picking one
 * confirms the match (records a payment). Advice only until the user confirms —
 * never auto-posts.
 */
function SuggestionsDialog({
  transaction,
  onClose,
}: {
  transaction: BankTransaction
  onClose: () => void
}) {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const suggestions = useBankTransactionSuggestions(transaction.id)
  const confirm = useConfirmBankMatch()

  const pick = (id: BankTransactionId, invoiceId: number): void => {
    confirm.mutate(
      { id, invoice_id: invoiceId },
      {
        onSuccess: (out) => {
          onClose()
          showToast({
            tone: 'ok',
            title: t('admin.bankReconciliation.confirm.done'),
            description: t('admin.bankReconciliation.confirm.doneBody', {
              amount: formatYen(out.payment.amount_cents),
            }),
          })
        },
        onError: () => {
          showToast({ tone: 'err', title: t('admin.bankReconciliation.confirm.error') })
        },
      },
    )
  }

  return (
    <div className="fixed inset-0 z-modal flex items-center justify-center bg-surface-overlay/70 px-inline-md">
      <button
        type="button"
        aria-label={t('common.actions.close')}
        className="absolute inset-0 size-full cursor-default"
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-modal="true"
        className="relative w-full max-w-2xl rounded-md border border-border bg-surface-raised px-inline-lg py-stack-lg shadow-md"
      >
        <Stack gap="md">
          <div>
            <Text as="h2" variant="heading-sm">
              {t('admin.bankReconciliation.confirm.title')}
            </Text>
            <Text variant="muted">
              {t('admin.bankReconciliation.confirm.subtitle', {
                payer: transaction.payer_name ?? transaction.description ?? '—',
                amount: formatYen(transaction.amount_cents),
              })}
            </Text>
          </div>

          {suggestions.isPending && (
            <LoadingState message={t('admin.bankReconciliation.confirm.loading')} />
          )}

          {suggestions.isError && (
            <Text variant="muted" role="alert">
              {t('admin.bankReconciliation.confirm.loadError')}
            </Text>
          )}

          {suggestions.isSuccess && suggestions.data.length === 0 && (
            <EmptyState message={t('admin.bankReconciliation.confirm.empty')} />
          )}

          {suggestions.isSuccess && suggestions.data.length > 0 && (
            <div className="table-scroll">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>{t('admin.bankReconciliation.confirm.col.invoice')}</th>
                    <th>{t('admin.bankReconciliation.confirm.col.client')}</th>
                    <th className="tr">{t('admin.bankReconciliation.confirm.col.outstanding')}</th>
                    <th>{t('admin.bankReconciliation.confirm.col.reasons')}</th>
                    <th className="tr">{t('admin.bankReconciliation.confirm.col.action')}</th>
                  </tr>
                </thead>
                <tbody>
                  {suggestions.data.map((s) => (
                    <tr key={s.invoice_id}>
                      <td>{s.invoice_number ?? `#${String(s.invoice_id)}`}</td>
                      <td>{s.client_name ?? `#${String(s.client_id)}`}</td>
                      <td className="tr num">{formatYen(s.outstanding_cents)}</td>
                      <td>{s.reasons.join(' / ')}</td>
                      <td className="tr">
                        <Button
                          size="sm"
                          disabled={confirm.isPending}
                          onClick={() => {
                            pick(transaction.id, s.invoice_id)
                          }}
                        >
                          {t('admin.bankReconciliation.confirm.apply')}
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <Stack direction="row" gap="sm" className="justify-end">
            <Button variant="ghost" size="sm" onClick={onClose} disabled={confirm.isPending}>
              {t('common.actions.close')}
            </Button>
          </Stack>
        </Stack>
      </div>
    </div>
  )
}
