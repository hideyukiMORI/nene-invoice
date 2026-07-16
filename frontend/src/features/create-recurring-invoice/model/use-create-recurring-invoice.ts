import { zodResolver } from '@hookform/resolvers/zod'
import { useState, type SyntheticEvent } from 'react'
import {
  useFieldArray,
  useForm,
  type UseFieldArrayReturn,
  type UseFormReturn,
} from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useClientList, useCreateClient, type Client } from '@/entities/client'
import { useLineItemSuggestions, type LineItemSuggestion } from '@/entities/line-item'
import { useCreateRecurringInvoice as useCreateRecurringInvoiceMutation } from '@/entities/recurring-invoice'
import { useTranslation } from '@/shared/i18n'
import { useDebouncedValue } from '@/shared/lib/use-debounced-value'
import { useToast } from '@/shared/ui'

const lineSchema = z.object({
  description: z.string().min(1),
  quantity: z.number().int().min(1),
  unit_price_cents: z.number().int().min(0),
  tax_rate_bps: z.union([z.literal(800), z.literal(1000)]),
})

const schema = z.object({
  client_id: z.number().int().positive(),
  name: z.string().min(1),
  frequency: z.enum(['monthly', 'quarterly']),
  first_run_on: z.string().min(1),
  is_active: z.boolean(),
  line_items: z.array(lineSchema).min(1),
  notes: z.string(),
})

export type CreateRecurringInvoiceFormValues = z.infer<typeof schema>

const EMPTY_LINE = {
  description: '',
  quantity: 1,
  unit_price_cents: 0,
  tax_rate_bps: 1000,
} as const satisfies CreateRecurringInvoiceFormValues['line_items'][number]

export interface UseCreateRecurringInvoice {
  form: UseFormReturn<CreateRecurringInvoiceFormValues>
  lines: UseFieldArrayReturn<CreateRecurringInvoiceFormValues, 'line_items'>
  clients: Client[]
  clientsLoading: boolean
  /** Inline-registers a new client (name + optional reading); resolves to its id (or null). */
  createClient: (name: string, nameKana: string | null) => Promise<number | null>
  /** History-based line-item suggestions (description + default price/rate). */
  lineSuggestions: LineItemSuggestion[]
  /** Drives server-side client search (debounced) for the picker. */
  onClientQueryChange: (query: string) => void
  onSubmit: (event: SyntheticEvent) => void
  addLine: () => void
  isPending: boolean
  errorMessage: string | null
}

export function useCreateRecurringInvoice(): UseCreateRecurringInvoice {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const navigate = useNavigate()
  const create = useCreateRecurringInvoiceMutation()
  const createClientMutation = useCreateClient()
  const [clientQuery, setClientQuery] = useState('')
  const debouncedClientQuery = useDebouncedValue(clientQuery)
  const clientList = useClientList({
    limit: 100,
    offset: 0,
    filters: { q: debouncedClientQuery.trim() === '' ? null : debouncedClientQuery.trim() },
    sort: { field: null, order: 'asc' },
  })
  const lineSuggestions = useLineItemSuggestions()

  const form = useForm<CreateRecurringInvoiceFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      client_id: 0,
      name: '',
      frequency: 'monthly',
      first_run_on: '',
      is_active: true,
      line_items: [EMPTY_LINE],
      notes: '',
    },
  })

  const lines = useFieldArray({ control: form.control, name: 'line_items' })

  const submit = form.handleSubmit((values) => {
    create.mutate(
      {
        client_id: values.client_id,
        name: values.name,
        frequency: values.frequency,
        first_run_on: values.first_run_on,
        line_items: values.line_items,
        is_active: values.is_active,
        notes: values.notes === '' ? null : values.notes,
      },
      {
        onSuccess: () => {
          void navigate('/recurring')
        },
      },
    )
  })

  const createClient = async (name: string, nameKana: string | null): Promise<number | null> => {
    try {
      const client = await createClientMutation.mutateAsync({
        name,
        name_kana: nameKana,
        contact_name: null,
        email: null,
        billing_address: null,
        registration_number: null,
      })
      showToast({ tone: 'ok', title: t('admin.clientPicker.registered', { name }) })
      return client.id
    } catch {
      showToast({ tone: 'err', title: t('admin.clientPicker.registerError') })
      return null
    }
  }

  return {
    form,
    lines,
    clients: clientList.data?.items ?? [],
    clientsLoading: clientList.isPending,
    createClient,
    lineSuggestions: lineSuggestions.data ?? [],
    onClientQueryChange: setClientQuery,
    onSubmit: (event) => {
      void submit(event)
    },
    addLine: () => {
      lines.append({ ...EMPTY_LINE })
    },
    isPending: create.isPending,
    errorMessage: create.isError ? t('admin.recurring.create.error') : null,
  }
}
