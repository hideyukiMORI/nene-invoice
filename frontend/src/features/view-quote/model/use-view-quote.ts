import { useNavigate } from 'react-router-dom'
import {
  useChangeQuoteStatus,
  useConvertQuote,
  useQuote,
  type QuoteId,
  type QuoteStatus,
  type QuoteWithLines,
} from '@/entities/quote'
import { useTranslation } from '@/shared/i18n'

export type ViewQuoteState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      quote: QuoteWithLines
      canSend: boolean
      canAccept: boolean
      canReject: boolean
      canExpire: boolean
      canConvert: boolean
      changeStatus: (status: QuoteStatus) => void
      convertToInvoice: () => void
      isStatusPending: boolean
      isConverting: boolean
      actionError: string | null
    }

export function useViewQuote(quoteId: QuoteId): ViewQuoteState {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const query = useQuote(quoteId)
  const changeStatus = useChangeQuoteStatus()
  const convert = useConvertQuote()

  if (query.isPending) return { kind: 'loading' }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }

  const quote = query.data
  const status = quote.status

  const actionError = changeStatus.isError
    ? t('admin.quotes.action.error')
    : convert.isError
      ? t('admin.quotes.detail.convertError')
      : null

  return {
    kind: 'ready',
    quote,
    canSend: status === 'draft',
    canAccept: status === 'sent',
    canReject: status === 'sent',
    canExpire: status === 'sent',
    canConvert: status === 'accepted',
    changeStatus: (newStatus) => {
      changeStatus.mutate({ id: quoteId, status: newStatus })
    },
    convertToInvoice: () => {
      convert.mutate(quoteId, {
        onSuccess: (invoice) => {
          void navigate(`/invoices/${String(invoice.id)}`)
        },
      })
    },
    isStatusPending: changeStatus.isPending,
    isConverting: convert.isPending,
    actionError,
  }
}
