import { useParams } from 'react-router-dom'
import { toQuoteId } from '@/entities/quote'
import { ViewQuote } from '@/features/view-quote'

export function QuoteDetailPage() {
  const { id } = useParams<{ id: string }>()
  const quoteId = toQuoteId(Number(id ?? '0'))
  return <ViewQuote quoteId={quoteId} />
}
