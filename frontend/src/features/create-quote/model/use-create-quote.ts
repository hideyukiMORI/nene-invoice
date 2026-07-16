import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useRef, useState, type SyntheticEvent } from 'react'
import {
  useFieldArray,
  useForm,
  type UseFieldArrayReturn,
  type UseFormReturn,
} from 'react-hook-form'
import { useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useClientList, useCreateClient, type Client } from '@/entities/client'
import { useLineItemSuggestions, type LineItemSuggestion } from '@/entities/line-item'
import { useCreateQuote as useCreateQuoteMutation, type CreateQuoteInput } from '@/entities/quote'
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

/** Router state for "duplicate this quote" (#316) — a create-input snapshot. */
export interface CreateQuoteLocationState {
  duplicate?: CreateQuoteInput
}

const toFormLine = (
  line: CreateQuoteInput['line_items'][number],
): CreateQuoteFormValues['line_items'][number] => ({
  description: line.description,
  quantity: line.quantity,
  unit_price_cents: line.unit_price_cents,
  // Tax rate is a default copied from the source; keep it within the form's set.
  tax_rate_bps: line.tax_rate_bps === 800 ? 800 : 1000,
})

/**
 * Default form values, optionally seeded from a duplicate snapshot. client_id
 * starts at 0 and is applied after clients load (see hook) so the picker can
 * show the name and a since-deleted client cleanly drops to "unselected".
 * valid_until is intentionally not copied (a past date would be wrong) — it
 * falls back to the issuer default at issue time.
 */
function buildDefaults(prefill: CreateQuoteInput | undefined): CreateQuoteFormValues {
  if (prefill === undefined) {
    return { client_id: 0, line_items: [EMPTY_LINE], valid_until: '', notes: '' }
  }

  return {
    client_id: 0,
    line_items: prefill.line_items.length > 0 ? prefill.line_items.map(toFormLine) : [EMPTY_LINE],
    valid_until: '',
    notes: prefill.notes ?? '',
  }
}

export interface UseCreateQuote {
  form: UseFormReturn<CreateQuoteFormValues>
  lines: UseFieldArrayReturn<CreateQuoteFormValues, 'line_items'>
  clients: Client[]
  clientsLoading: boolean
  /** Inline-registers a new client (name + optional reading); resolves to its id (or null). */
  createClient: (name: string, nameKana: string | null) => Promise<number | null>
  /** History-based line-item suggestions (description + default price/rate). */
  lineSuggestions: LineItemSuggestion[]
  /** Drives server-side client search (debounced) for the picker (#328). */
  onClientQueryChange: (query: string) => void
  onSubmit: (event: SyntheticEvent) => void
  addLine: () => void
  isPending: boolean
  errorMessage: string | null
}

export function useCreateQuote(): UseCreateQuote {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const navigate = useNavigate()
  const create = useCreateQuoteMutation()
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

  const location = useLocation()
  const prefill = (location.state as CreateQuoteLocationState | null)?.duplicate

  const form = useForm<CreateQuoteFormValues>({
    resolver: zodResolver(schema),
    defaultValues: buildDefaults(prefill),
  })

  const lines = useFieldArray({ control: form.control, name: 'line_items' })

  // Apply the duplicated client only once clients have loaded: this flips
  // client_id 0 → id so the picker syncs the name, and a since-deleted client
  // (not in the list) stays unselected so the operator re-picks. Runs once.
  const clientApplied = useRef(false)
  useEffect(() => {
    if (clientApplied.current || clientList.isPending) return
    clientApplied.current = true
    const wanted = prefill?.client_id
    if (
      wanted !== undefined &&
      wanted !== 0 &&
      (clientList.data?.items ?? []).some((c) => c.id === wanted)
    ) {
      form.setValue('client_id', wanted)
    }
  }, [clientList.isPending, clientList.data, prefill, form])

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
    errorMessage: create.isError ? t('admin.quotes.create.error') : null,
  }
}
