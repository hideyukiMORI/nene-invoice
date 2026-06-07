import { useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { CsvImportReport } from '@/shared/lib/csv-import'
import { useExportCsvBase, type UseExportCsv } from '@/shared/api/use-export-csv'
import { clientKeys } from './query-keys'

/** Downloads the clients import template (header only). */
export function useDownloadClientsImportTemplate(): UseExportCsv {
  return useExportCsvBase(
    '/admin/clients/import-template',
    'clients-import-template.csv',
    'common.csvImport.templateError',
  )
}

/**
 * Posts a template CSV to the import endpoint and resolves the report. A `dryRun`
 * validates without writing; a real apply (accepted) invalidates the client
 * lists so the table reflects the new/updated rows.
 */
export function useImportClients(): (csv: string, dryRun: boolean) => Promise<CsvImportReport> {
  const queryClient = useQueryClient()

  return async (csv, dryRun) => {
    const path = dryRun ? '/admin/clients/import?dry_run=1' : '/admin/clients/import'
    const report = await apiClient.postCsv<CsvImportReport>(path, csv)
    if (!dryRun && report.accepted) {
      void queryClient.invalidateQueries({ queryKey: clientKeys.lists() })
    }
    return report
  }
}
