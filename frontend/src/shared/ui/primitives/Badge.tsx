import type { ReactNode } from 'react'
import { cn } from '@/shared/lib/cn'

/** Semantic colour of a status chip — maps to the .badge-* component classes. */
export type BadgeTone = 'neutral' | 'info' | 'ok' | 'warn' | 'danger' | 'brand'

export interface BadgeProps {
  tone?: BadgeTone
  className?: string
  children: ReactNode
}

const TONE: Record<BadgeTone, string> = {
  neutral: 'badge-neutral',
  info: 'badge-info',
  ok: 'badge-ok',
  warn: 'badge-warn',
  danger: 'badge-danger',
  brand: 'badge-brand',
}

/** Square status chip (案C). Colour conveys meaning; text labels it. */
export function Badge({ tone = 'neutral', className, children }: BadgeProps) {
  return <span className={cn('badge', TONE[tone], className)}>{children}</span>
}
