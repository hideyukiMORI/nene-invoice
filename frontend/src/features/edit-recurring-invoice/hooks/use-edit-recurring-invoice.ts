import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useRef, useState, type SyntheticEvent } from 'react'
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
import {
  useRecurringInvoice,
  useUpdateRecurringInvoice,
  type RecurringInvoiceId,
} from '@/entities/recurring-invoice'
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
  next_run_on: z.string().min(1),
  is_active: z.boolean(),
  line_items: z.array(lineSchema).min(1),
  notes: z.string(),
})

export type EditRecurringInvoiceFormValues = z.infer<typeof schema>

const EMPTY_LINE = {
  description: '',
  quantity: 1,
  unit_price_cents: 0,
  tax_rate_bps: 1000,
} as const satisfies EditRecurringInvoiceFormValues['line_items'][number]

export interface EditRecurringInvoiceReady {
  kind: 'ready'
  form: UseFormReturn<EditRecurringInvoiceFormValues>
  lines: UseFieldArrayReturn<EditRecurringInvoiceFormValues, 'line_items'>
  clients: Client[]
  clientsLoading: boolean
  createClient: (name: string, nameKana: string | null) => Promise<number | null>
  lineSuggestions: LineItemSuggestion[]
  onClientQueryChange: (query: string) => void
  onSubmit: (event: SyntheticEvent) => void
  addLine: () => void
  isPending: boolean
  errorMessage: string | null
}

export type EditRecurringInvoiceState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | EditRecurringInvoiceReady

export function useEditRecurringInvoice(
  recurringInvoiceId: RecurringInvoiceId,
): EditRecurringInvoiceState {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const navigate = useNavigate()
  const query = useRecurringInvoice(recurringInvoiceId)
  const update = useUpdateRecurringInvoice()
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

  const form = useForm<EditRecurringInvoiceFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      client_id: 0,
      name: '',
      frequency: 'monthly',
      next_run_on: '',
      is_active: true,
      line_items: [EMPTY_LINE],
      notes: '',
    },
  })

  const lines = useFieldArray({ control: form.control, name: 'line_items' })

  // Prefill once the schedule loads (external sync → effect). Guarded so the
  // operator's edits are not clobbered by a background refetch.
  const recurring = query.data
  const { reset } = form
  const prefilled = useRef(false)
  useEffect(() => {
    if (recurring === undefined || prefilled.current) return
    prefilled.current = true
    reset({
      client_id: recurring.client_id,
      name: recurring.name,
      frequency: recurring.frequency,
      next_run_on: recurring.next_run_on,
      is_active: recurring.is_active,
      line_items:
        recurring.line_items.length > 0
          ? recurring.line_items.map((l) => ({
              description: l.description,
              quantity: l.quantity,
              unit_price_cents: l.unit_price_cents,
              tax_rate_bps: l.tax_rate_bps === 800 ? 800 : 1000,
            }))
          : [EMPTY_LINE],
    })
  }, [recurring, reset])

  if (query.isPending) {
    return { kind: 'loading' }
  }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }

  const submit = form.handleSubmit((values) => {
    update.mutate(
      {
        id: recurringInvoiceId,
        client_id: values.client_id,
        name: values.name,
        frequency: values.frequency,
        next_run_on: values.next_run_on,
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
    kind: 'ready',
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
    isPending: update.isPending,
    errorMessage: update.isError ? t('admin.recurring.edit.error') : null,
  }
}
