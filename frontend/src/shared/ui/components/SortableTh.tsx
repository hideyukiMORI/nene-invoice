import { cn } from '@/shared/lib/cn'

/**
 * Sortable table header cell (design 03). Click toggles asc/desc; the active
 * column is highlighted (deep green) and carries `aria-sort`, with a paired
 * up/down chevron whose active half is emphasised.
 */
export interface SortableThProps {
  label: string
  /** True when this column is the active sort key. */
  active: boolean
  order: 'asc' | 'desc'
  /** Right-aligned (numeric) columns mirror the label/chevron order. */
  right?: boolean
  onToggle: () => void
}

export function SortableTh({ label, active, order, right = false, onToggle }: SortableThProps) {
  const ariaSort = active ? (order === 'asc' ? 'ascending' : 'descending') : 'none'

  return (
    <th className={cn('sortable', right && 'tr')} aria-sort={ariaSort}>
      <button type="button" className="th-in" onClick={onToggle}>
        {label}
        <svg className="sort-ico" viewBox="0 0 10 14" aria-hidden="true">
          <path className="up" d="M5 0l3.6 5H1.4z" />
          <path className="dn" d="M5 14l3.6-5H1.4z" />
        </svg>
      </button>
    </th>
  )
}
