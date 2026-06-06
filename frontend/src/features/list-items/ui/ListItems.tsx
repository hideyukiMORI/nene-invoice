import { useState, type ReactNode, type SyntheticEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  EMPTY_ITEM_FILTERS,
  useDeleteItem,
  type Item,
  type ItemListFilters,
  type ItemSortField,
} from '@/entities/item'
import { useTranslation } from '@/shared/i18n'
import { KbdHint, useRowCursor } from '@/shared/keyboard'
import { formatTaxRate, formatYen } from '@/shared/lib/format-money'
import {
  ConfirmDialog,
  EmptyState,
  ErrorState,
  Field,
  FilterBar,
  Input,
  LinkButton,
  LoadingState,
  SortableTh,
  Stack,
  Text,
} from '@/shared/ui'
import { useListItems } from '../hooks/use-list-items'

const trimmedOrNull = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/** Item master (品目) list screen with search / sort and per-row delete. */
export function ListItems() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const view = useListItems()
  const deleteItem = useDeleteItem()
  const [pendingDelete, setPendingDelete] = useState<Item | null>(null)
  const [draft, setDraft] = useState<ItemListFilters>(EMPTY_ITEM_FILTERS)

  const rows = view.state.kind === 'ready' ? view.state.items : []
  const cursor = useRowCursor(rows.length, (index) => {
    const row = rows[index]
    if (row !== undefined) void navigate(`/items/${String(row.id)}/edit`)
  })

  const confirmDelete = (): void => {
    if (pendingDelete === null) return
    deleteItem.mutate(pendingDelete.id, {
      onSuccess: () => {
        setPendingDelete(null)
      },
    })
  }

  const onSubmit = (event: SyntheticEvent): void => {
    event.preventDefault()
    view.applyFilters(draft)
  }
  const onReset = (): void => {
    setDraft(EMPTY_ITEM_FILTERS)
    view.resetFilters()
  }

  const sortableTh = (field: ItemSortField, label: string): ReactNode => (
    <SortableTh
      label={label}
      active={view.sort.field === field}
      order={view.sort.order}
      onToggle={() => {
        view.toggleSort(field)
      }}
    />
  )

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.items.title')}
        </Text>
        <LinkButton to="/items/new" size="sm" aria-keyshortcuts="n">
          {t('admin.items.newButton')}
        </LinkButton>
      </div>

      <FilterBar count={view.total} onSubmit={onSubmit} onReset={onReset}>
        <Field id="item-q" label={t('admin.items.filter.search')}>
          <div className="field-kbd">
            <Input
              id="item-q"
              data-kbd="search"
              aria-keyshortcuts="/"
              className="pr-9"
              value={draft.q ?? ''}
              placeholder={t('admin.items.filter.searchPlaceholder')}
              onChange={(e) => {
                setDraft({ ...draft, q: trimmedOrNull(e.target.value) })
              }}
            />
            <KbdHint>/</KbdHint>
          </div>
        </Field>
      </FilterBar>

      {view.state.kind === 'loading' && <LoadingState message={t('admin.items.loading')} />}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.items.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.items.empty')} />}

      {view.state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                {sortableTh('description', t('admin.items.col.description'))}
                {sortableTh('unit_price', t('admin.items.col.unitPrice'))}
                {sortableTh('tax_rate', t('admin.items.col.taxRate'))}
                <th className="tr">{t('admin.items.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {view.state.items.map((item, index) => (
                <tr
                  key={item.id}
                  data-kbd-row={index}
                  className={cursor === index ? 'is-cursor' : undefined}
                >
                  <td data-label={t('admin.items.col.description')}>{item.description}</td>
                  <td className="num" data-label={t('admin.items.col.unitPrice')}>
                    {formatYen(item.default_unit_price_cents)}
                  </td>
                  <td className="num" data-label={t('admin.items.col.taxRate')}>
                    {formatTaxRate(item.default_tax_rate_bps)}
                  </td>
                  <td className="tr" data-label={t('admin.items.col.actions')}>
                    <Stack direction="row" gap="sm" className="justify-end">
                      <Link to={`/items/${String(item.id)}/edit`} className="text-body text-accent">
                        {t('admin.items.editButton')}
                      </Link>
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-body text-danger"
                        onClick={() => {
                          setPendingDelete(item)
                        }}
                      >
                        {t('admin.items.delete.action')}
                      </button>
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {deleteItem.isError && (
        <Text variant="muted" role="alert">
          {t('admin.items.delete.error')}
        </Text>
      )}

      {pendingDelete !== null && (
        <ConfirmDialog
          title={t('admin.items.delete.title')}
          message={t('admin.items.delete.message', { description: pendingDelete.description })}
          confirmLabel={t('admin.items.delete.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={deleteItem.isPending}
          onConfirm={confirmDelete}
          onCancel={() => {
            setPendingDelete(null)
          }}
        />
      )}
    </Stack>
  )
}
