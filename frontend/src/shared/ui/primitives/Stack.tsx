import type { ReactNode } from 'react'
import { cn } from '@/shared/lib/cn'

type StackGap = 'sm' | 'md' | 'lg'
type StackDirection = 'row' | 'column'

export interface StackProps {
  direction?: StackDirection
  gap?: StackGap
  className?: string
  children: ReactNode
}

const GAP: Record<StackGap, string> = {
  sm: 'gap-stack-sm',
  md: 'gap-stack-md',
  lg: 'gap-stack-lg',
}

const DIRECTION: Record<StackDirection, string> = {
  row: 'flex-row items-center',
  column: 'flex-col',
}

export function Stack({ direction = 'column', gap = 'md', className, children }: StackProps) {
  return <div className={cn('flex', DIRECTION[direction], GAP[gap], className)}>{children}</div>
}
