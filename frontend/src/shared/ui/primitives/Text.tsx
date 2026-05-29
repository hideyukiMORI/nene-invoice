import type { ReactNode } from 'react'
import { cn } from '@/shared/lib/cn'

type TextVariant = 'body' | 'muted' | 'heading-sm' | 'heading-md'

export interface TextProps {
  as?: 'p' | 'span' | 'h1' | 'h2' | 'h3'
  variant?: TextVariant
  id?: string
  role?: 'alert' | 'status'
  className?: string
  children: ReactNode
}

const VARIANT: Record<TextVariant, string> = {
  body: 'text-body text-fg',
  muted: 'text-body text-fg-muted',
  'heading-sm': 'text-heading-sm text-fg font-semibold',
  'heading-md': 'text-heading-md text-fg font-semibold',
}

export function Text({
  as: Tag = 'p',
  variant = 'body',
  id,
  role,
  className,
  children,
}: TextProps) {
  return (
    <Tag id={id} role={role} className={cn(VARIANT[variant], className)}>
      {children}
    </Tag>
  )
}
