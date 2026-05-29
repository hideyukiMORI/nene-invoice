import { cn } from '@/shared/lib/cn'

export interface SpinnerProps {
  label: string
  className?: string
}

/** Indeterminate loading indicator. `label` is required for screen readers. */
export function Spinner({ label, className }: SpinnerProps) {
  return (
    <span
      role="status"
      aria-label={label}
      className={cn(
        'inline-block size-4 animate-spin rounded-full border-2 border-border border-t-accent',
        className,
      )}
    />
  )
}
