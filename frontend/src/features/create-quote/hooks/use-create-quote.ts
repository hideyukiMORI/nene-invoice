import { zodResolver } from '@hookform/resolvers/zod'
import type { SyntheticEvent } from 'react'
import {
  useFieldArray,
  useForm,
  type UseFieldArrayReturn,
  type UseFormReturn,
} from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useClientList, type Client } from '@/entities/client'
import { useCreateQuote as useCreateQuoteMutation } from '@/entities/quote'
import { useTranslation } from '@/shared/i18n'

const lineSchema = z.object({
  description: z.string().min(1),
  quantity: z.number().int().min(1),
  unit_price_cents: z.number().int().min(0),
  tax_rate_bps: z.union([z.literal(800), z.literal(1000)]),
})

const schema = z.object({
  client_id: z.number().int().positive(),
  line_items: z.array(lineSchema).min(1),
  valid_until: z.string(),
  notes: z.string(),
})

export type CreateQuoteFormValues = z.infer<typeof schema>

const EMPTY_LINE = {
  description: '',
  quantity: 1,
  unit_price_cents: 0,
  tax_rate_bps: 1000,
} as const satisfies CreateQuoteFormValues['line_items'][number]

export interface UseCreateQuote {
  form: UseFormReturn<CreateQuoteFormValues>
  lines: UseFieldArrayReturn<CreateQuoteFormValues, 'line_items'>
  clients: Client[]
  clientsLoading: boolean
  onSubmit: (event: SyntheticEvent) => void
  addLine: () => void
  isPending: boolean
  errorMessage: string | null
}

export function useCreateQuote(): UseCreateQuote {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateQuoteMutation()
  const clientList = useClientList({ limit: 100, offset: 0 })

  const form = useForm<CreateQuoteFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { client_id: 0, line_items: [EMPTY_LINE], valid_until: '', notes: '' },
  })

  const lines = useFieldArray({ control: form.control, name: 'line_items' })

  const submit = form.handleSubmit((values) => {
    create.mutate(
      {
        client_id: values.client_id,
        line_items: values.line_items,
        valid_until: values.valid_until === '' ? null : values.valid_until,
        notes: values.notes === '' ? null : values.notes,
      },
      {
        onSuccess: (quote) => {
          void navigate(`/quotes/${String(quote.id)}`)
        },
      },
    )
  })

  return {
    form,
    lines,
    clients: clientList.data?.items ?? [],
    clientsLoading: clientList.isPending,
    onSubmit: (event) => {
      void submit(event)
    },
    addLine: () => {
      lines.append({ ...EMPTY_LINE })
    },
    isPending: create.isPending,
    errorMessage: create.isError ? t('admin.quotes.create.error') : null,
  }
}
