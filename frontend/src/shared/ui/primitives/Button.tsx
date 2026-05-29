import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { cn } from '@/shared/lib/cn'

type ButtonVariant = 'primary' | 'danger' | 'ghost'
type ButtonSize = 'sm' | 'md'

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  size?: ButtonSize
  children: ReactNode
}

const VARIANT: Record<ButtonVariant, string> = {
  primary: 'bg-accent text-fg-inverse hover:bg-accent-hover',
  danger: 'bg-danger text-fg-inverse',
  ghost: 'bg-surface-raised text-fg border border-border hover:bg-surface-overlay',
}

const SIZE: Record<ButtonSize, string> = {
  sm: 'px-inline-sm py-stack-xs',
  md: 'px-inline-md py-stack-sm',
}

export function Button({
  variant = 'primary',
  size = 'md',
  type = 'button',
  className,
  children,
  ...rest
}: ButtonProps) {
  return (
    <button
      type={type}
      className={cn(
        'inline-flex items-center justify-center rounded-md font-medium text-body',
        'transition-colors focus-visible:outline-2 focus-visible:outline-focus-ring',
        'disabled:cursor-not-allowed disabled:opacity-50',
        VARIANT[variant],
        SIZE[size],
        className,
      )}
      {...rest}
    >
      {children}
    </button>
  )
}
