import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, type SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useItem, useUpdateItem, type ItemId } from '@/entities/item'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  description: z.string().min(1),
  default_unit_price_cents: z.number().int().min(0),
  default_tax_rate_bps: z.union([z.literal(800), z.literal(1000)]),
})

export type EditItemFormValues = z.infer<typeof schema>

export type EditItemState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | {
      kind: 'ready'
      form: UseFormReturn<EditItemFormValues>
      onSubmit: (event: SyntheticEvent) => void
      isPending: boolean
      errorMessage: string | null
    }

export function useEditItem(itemId: ItemId): EditItemState {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const query = useItem(itemId)
  const update = useUpdateItem()

  const form = useForm<EditItemFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { description: '', default_unit_price_cents: 0, default_tax_rate_bps: 1000 },
  })

  // Prefill once the item loads (external sync → effect).
  const item = query.data
  const { reset } = form
  useEffect(() => {
    if (item !== undefined) {
      reset({
        description: item.description,
        default_unit_price_cents: item.default_unit_price_cents,
        default_tax_rate_bps: item.default_tax_rate_bps === 800 ? 800 : 1000,
      })
    }
  }, [item, reset])

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
      { id: itemId, ...values },
      {
        onSuccess: () => {
          void navigate('/items')
        },
      },
    )
  })

  return {
    kind: 'ready',
    form,
    onSubmit: (event) => {
      void submit(event)
    },
    isPending: update.isPending,
    errorMessage: update.isError ? t('admin.items.edit.error') : null,
  }
}
