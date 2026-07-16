import { zodResolver } from '@hookform/resolvers/zod'
import type { SyntheticEvent } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useCreateItem as useCreateItemMutation } from '@/entities/item'
import { useTranslation } from '@/shared/i18n'

const schema = z.object({
  description: z.string().min(1),
  default_unit_price_cents: z.number().int().min(0),
  default_tax_rate_bps: z.union([z.literal(800), z.literal(1000)]),
})

export type CreateItemFormValues = z.infer<typeof schema>

export interface UseCreateItem {
  form: UseFormReturn<CreateItemFormValues>
  onSubmit: (event: SyntheticEvent) => void
  isPending: boolean
  errorMessage: string | null
}

export function useCreateItem(): UseCreateItem {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateItemMutation()

  const form = useForm<CreateItemFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { description: '', default_unit_price_cents: 0, default_tax_rate_bps: 1000 },
  })

  const submit = form.handleSubmit((values) => {
    create.mutate(values, {
      onSuccess: () => {
        void navigate('/items')
      },
    })
  })

  return {
    form,
    onSubmit: (event) => {
      void submit(event)
    },
    isPending: create.isPending,
    errorMessage: create.isError ? t('admin.items.create.error') : null,
  }
}
