import type { Ref, SelectHTMLAttributes } from 'react'
import { cn } from '@/shared/lib/cn'

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  ref?: Ref<HTMLSelectElement>
}

export function Select({ className, ref, children, ...rest }: SelectProps) {
  return (
    <select
      ref={ref}
      className={cn(
        'block w-full rounded-md border border-border bg-surface-raised text-fg',
        'px-inline-sm py-stack-xs text-body',
        'focus-visible:outline-2 focus-visible:outline-focus-ring',
        'disabled:cursor-not-allowed disabled:opacity-50',
        className,
      )}
      {...rest}
    >
      {children}
    </select>
  )
}
