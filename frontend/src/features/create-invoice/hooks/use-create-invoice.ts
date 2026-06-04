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
import { useCreateInvoice as useCreateInvoiceMutation } from '@/entities/invoice'
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
  notes: z.string(),
})

export type CreateInvoiceFormValues = z.infer<typeof schema>

const EMPTY_LINE = {
  description: '',
  quantity: 1,
  unit_price_cents: 0,
  tax_rate_bps: 1000,
} as const satisfies CreateInvoiceFormValues['line_items'][number]

export interface UseCreateInvoice {
  form: UseFormReturn<CreateInvoiceFormValues>
  lines: UseFieldArrayReturn<CreateInvoiceFormValues, 'line_items'>
  clients: Client[]
  clientsLoading: boolean
  onSubmit: (event: SyntheticEvent) => void
  addLine: () => void
  isPending: boolean
  errorMessage: string | null
}

export function useCreateInvoice(): UseCreateInvoice {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateInvoiceMutation()
  const clientList = useClientList({
    limit: 100,
    offset: 0,
    filters: { q: null },
    sort: { field: null, order: 'asc' },
  })

  const form = useForm<CreateInvoiceFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { client_id: 0, line_items: [EMPTY_LINE], notes: '' },
  })

  const lines = useFieldArray({ control: form.control, name: 'line_items' })

  const submit = form.handleSubmit((values) => {
    create.mutate(
      {
        client_id: values.client_id,
        line_items: values.line_items,
        notes: values.notes === '' ? null : values.notes,
      },
      {
        onSuccess: (invoice) => {
          void navigate(`/invoices/${String(invoice.id)}`)
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
      lines.append(EMPTY_LINE)
    },
    isPending: create.isPending,
    errorMessage: create.isError ? t('admin.invoices.create.error') : null,
  }
}
