import { useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { CsvImportReport } from '@/shared/lib/csv-import'
import { useExportCsvBase, type UseExportCsv } from '@/shared/api/use-export-csv'
import { itemKeys } from './query-keys'

/** Downloads the items import template (header only). */
export function useDownloadItemsImportTemplate(): UseExportCsv {
  return useExportCsvBase(
    '/admin/items/import-template',
    'items-import-template.csv',
    'common.csvImport.templateError',
  )
}

/**
 * Posts a template CSV to the items import endpoint and resolves the report. A
 * `dryRun` validates without writing; a real apply (accepted) invalidates the
 * item lists.
 */
export function useImportItems(): (csv: string, dryRun: boolean) => Promise<CsvImportReport> {
  const queryClient = useQueryClient()

  return async (csv, dryRun) => {
    const path = dryRun ? '/admin/items/import?dry_run=1' : '/admin/items/import'
    const report = await apiClient.postCsv<CsvImportReport>(path, csv)
    if (!dryRun && report.accepted) {
      void queryClient.invalidateQueries({ queryKey: itemKeys.lists() })
    }
    return report
  }
}
