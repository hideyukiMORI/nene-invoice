import type { ReactNode, SyntheticEvent } from 'react'
import { useTranslation } from '@/shared/i18n'
import { Button } from '../primitives/Button'

export interface FilterBarProps {
  /** Number of matching records, shown as 「表示中 N 件」 in the footer. */
  count: number
  /** Submit handler — applies the draft filters to the query. */
  onSubmit: (event: SyntheticEvent) => void
  /** Resets the draft and the active query to their defaults. */
  onReset: () => void
  /** Filter fields, laid out by `.filter-grid`. */
  children: ReactNode
  /** Optional extra control rendered in the footer beside the count (left group). */
  footStart?: ReactNode
}

/**
 * Search / filter panel matching spec design 04 `id="audit"` (`.filter-bar`):
 * a card holding the field grid plus a ruled footer with the result count and
 * the reset / apply actions. Shared across every list screen so the filtering
 * UI stays identical (Issue #302).
 */
export function FilterBar({ count, onSubmit, onReset, children, footStart }: FilterBarProps) {
  const { t } = useTranslation()

  return (
    <form className="card filter-bar" onSubmit={onSubmit}>
      <div className="filter-grid">{children}</div>
      <div className="filter-foot">
        <span className="filter-count">
          {t('admin.filter.shownLabel')} <b>{count}</b> {t('admin.filter.shownUnit')}
        </span>
        {footStart}
        <span className="flex-1" />
        <Button type="button" variant="ghost" size="sm" onClick={onReset}>
          {t('admin.filter.reset')}
        </Button>
        <Button type="submit" size="sm">
          {t('admin.filter.apply')}
        </Button>
      </div>
    </form>
  )
}
