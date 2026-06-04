import type { Ref, TextareaHTMLAttributes } from 'react'
import { cn } from '@/shared/lib/cn'

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  ref?: Ref<HTMLTextAreaElement>
}

export function Textarea({ className, ref, rows = 4, ...rest }: TextareaProps) {
  return (
    <textarea
      ref={ref}
      rows={rows}
      className={cn(
        'block w-full rounded-md border border-border bg-surface-raised text-fg',
        'px-inline-sm py-stack-xs text-body',
        'focus-visible:outline-2 focus-visible:outline-focus-ring',
        'disabled:cursor-not-allowed disabled:opacity-50',
        'aria-invalid:border-danger', // 型1 field error — red border
        className,
      )}
      {...rest}
    />
  )
}
