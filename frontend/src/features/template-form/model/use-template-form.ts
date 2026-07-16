import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, type SyntheticEvent } from 'react'
import {
  useFieldArray,
  useForm,
  type UseFieldArrayReturn,
  type UseFormReturn,
} from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import {
  useCreateTemplate,
  useTemplate,
  useUpdateTemplate,
  type TemplateId,
} from '@/entities/template'
import { useTranslation } from '@/shared/i18n'

const lineSchema = z.object({
  // Lenient here; blank lines are dropped on submit so a notes-only template works.
  description: z.string(),
  quantity: z.number().int().min(1),
  unit_price_cents: z.number().int().min(0),
  tax_rate_bps: z.union([z.literal(800), z.literal(1000)]),
})

const schema = z.object({
  name: z.string().min(1),
  notes: z.string(),
  line_items: z.array(lineSchema),
})

export type TemplateFormValues = z.infer<typeof schema>

const EMPTY_LINE = {
  description: '',
  quantity: 1,
  unit_price_cents: 0,
  tax_rate_bps: 1000,
} as const satisfies TemplateFormValues['line_items'][number]

export type TemplateFormState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      form: UseFormReturn<TemplateFormValues>
      lines: UseFieldArrayReturn<TemplateFormValues, 'line_items'>
      addLine: () => void
      onSubmit: (event: SyntheticEvent) => void
      isPending: boolean
      errorMessage: string | null
      isEdit: boolean
    }

/** Shared create/edit template form. Pass an id to edit; omit to create. */
export function useTemplateForm(templateId?: TemplateId): TemplateFormState {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const isEdit = templateId !== undefined
  const create = useCreateTemplate()
  const update = useUpdateTemplate()
  const query = useTemplate(templateId ?? (0 as TemplateId))

  const form = useForm<TemplateFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', notes: '', line_items: [EMPTY_LINE] },
  })
  const lines = useFieldArray({ control: form.control, name: 'line_items' })

  // Prefill once the template loads (edit mode only).
  const template = isEdit ? query.data : undefined
  const { reset } = form
  useEffect(() => {
    if (template !== undefined) {
      reset({
        name: template.name,
        notes: template.notes ?? '',
        line_items:
          template.line_items.length > 0
            ? template.line_items.map((l) => ({
                description: l.description,
                quantity: l.quantity,
                unit_price_cents: l.unit_price_cents,
                tax_rate_bps: l.tax_rate_bps === 800 ? 800 : 1000,
              }))
            : [EMPTY_LINE],
      })
    }
  }, [template, reset])

  if (isEdit && query.isPending) {
    return { kind: 'loading' }
  }
  if (isEdit && query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }

  const submit = form.handleSubmit((values) => {
    // Drop blank rows so a notes-only template is valid; trim descriptions.
    const cleanedLines = values.line_items
      .filter((l) => l.description.trim() !== '')
      .map((l) => ({
        description: l.description.trim(),
        quantity: l.quantity,
        unit_price_cents: l.unit_price_cents,
        tax_rate_bps: l.tax_rate_bps,
      }))

    const payload = {
      name: values.name,
      notes: values.notes === '' ? null : values.notes,
      line_items: cleanedLines,
    }

    const onSuccess = (): void => {
      void navigate('/templates')
    }

    if (templateId === undefined) {
      create.mutate(payload, { onSuccess })
    } else {
      update.mutate({ id: templateId, ...payload }, { onSuccess })
    }
  })

  const mutation = isEdit ? update : create

  return {
    kind: 'ready',
    form,
    lines,
    addLine: () => {
      lines.append({ ...EMPTY_LINE })
    },
    onSubmit: (event) => {
      void submit(event)
    },
    isPending: mutation.isPending,
    errorMessage: mutation.isError ? t('admin.templates.form.error') : null,
    isEdit,
  }
}
