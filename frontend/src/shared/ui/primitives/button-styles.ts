import { cn } from '@/shared/lib/cn'

export type ButtonVariant = 'primary' | 'danger' | 'ghost'
export type ButtonSize = 'sm' | 'md'

const VARIANT: Record<ButtonVariant, string> = {
  primary: 'bg-accent text-fg-inverse hover:bg-accent-hover',
  danger: 'bg-danger text-fg-inverse',
  ghost: 'bg-surface-raised text-fg border border-border hover:bg-surface-overlay',
}

const SIZE: Record<ButtonSize, string> = {
  sm: 'px-inline-sm py-stack-xs',
  md: 'px-inline-md py-stack-sm',
}

/**
 * Shared button appearance. Used by `Button` and by `LinkButton` so a
 * navigating action (react-router `Link`) looks identical to a real button.
 */
export function buttonClassNames(
  variant: ButtonVariant = 'primary',
  size: ButtonSize = 'md',
  className?: string,
): string {
  return cn(
    'inline-flex items-center justify-center rounded-md font-medium text-body no-underline',
    'transition-colors focus-visible:outline-2 focus-visible:outline-focus-ring',
    'disabled:cursor-not-allowed disabled:opacity-50',
    VARIANT[variant],
    SIZE[size],
    className,
  )
}
