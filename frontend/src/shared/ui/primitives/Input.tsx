import type { InputHTMLAttributes, Ref } from 'react'
import { cn } from '@/shared/lib/cn'

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  ref?: Ref<HTMLInputElement>
}

export function Input({ className, ref, ...rest }: InputProps) {
  return (
    <input
      ref={ref}
      className={cn(
        'block w-full rounded-md border border-border bg-surface-raised text-fg',
        'px-inline-sm py-stack-xs text-body',
        'focus-visible:outline-2 focus-visible:outline-focus-ring',
        'disabled:cursor-not-allowed disabled:opacity-50',
        className,
      )}
      {...rest}
    />
  )
}
