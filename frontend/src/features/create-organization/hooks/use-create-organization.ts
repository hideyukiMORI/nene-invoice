import { zodResolver } from '@hookform/resolvers/zod'
import type { SyntheticEvent } from 'react'
import { useForm, useWatch, type UseFormReturn } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { useCreateOrganization as useCreateOrganizationMutation } from '@/entities/organization'
import { useTranslation } from '@/shared/i18n'

/** The initial-admin password floor mirrors the backend operator policy. */
const ADMIN_PASSWORD_MIN = 12

const schema = z
  .object({
    name: z.string().min(1),
    slug: z.string().min(1),
    plan: z.string(),
    createAdmin: z.boolean(),
    adminEmail: z.string(),
    adminPassword: z.string(),
  })
  // The admin fields only matter when "create an admin now" is on; then the
  // email must be valid and the password must meet the minimum length.
  .refine((values) => !values.createAdmin || z.email().safeParse(values.adminEmail).success, {
    path: ['adminEmail'],
    message: 'invalid_email',
  })
  .refine((values) => !values.createAdmin || values.adminPassword.length >= ADMIN_PASSWORD_MIN, {
    path: ['adminPassword'],
    message: 'password_too_short',
  })

export type CreateOrganizationFormValues = z.infer<typeof schema>

export interface UseCreateOrganization {
  form: UseFormReturn<CreateOrganizationFormValues>
  createAdmin: boolean
  onSubmit: (event: SyntheticEvent) => void
  isPending: boolean
  errorMessage: string | null
}

export function useCreateOrganization(): UseCreateOrganization {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateOrganizationMutation()

  const form = useForm<CreateOrganizationFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '',
      slug: '',
      plan: '',
      createAdmin: false,
      adminEmail: '',
      adminPassword: '',
    },
  })

  const createAdmin = useWatch({ control: form.control, name: 'createAdmin' })

  const submit = form.handleSubmit((values) => {
    create.mutate(
      {
        name: values.name,
        slug: values.slug,
        plan: values.plan === '' ? undefined : values.plan,
        adminEmail: values.createAdmin ? values.adminEmail : undefined,
        adminPassword: values.createAdmin ? values.adminPassword : undefined,
      },
      {
        onSuccess: () => {
          void navigate('/organizations')
        },
      },
    )
  })

  return {
    form,
    createAdmin,
    onSubmit: (event) => {
      void submit(event)
    },
    isPending: create.isPending,
    errorMessage: create.isError ? t('admin.organizations.create.error') : null,
  }
}
