import { useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import { useExportCsvBase, type UseExportCsv } from '@/shared/api/use-export-csv'
import { clientKeys } from './query-keys'

export interface ClientImportRowError {
  row: number
  column: string | null
  code: string
  message: string
}

export interface ClientImportReport {
  accepted: boolean
  dry_run: boolean
  format_error: string | null
  summary: { rows: number; created: number; updated: number; errors: number }
  errors: ClientImportRowError[]
}

/** Downloads the clients import template (header only). */
export function useDownloadClientsImportTemplate(): UseExportCsv {
  return useExportCsvBase(
    '/admin/clients/import-template',
    'clients-import-template.csv',
    'admin.clients.import.templateError',
  )
}

/**
 * Posts a template CSV to the import endpoint and resolves the report. A `dryRun`
 * validates without writing; a real apply (accepted) invalidates the client
 * lists so the table reflects the new/updated rows.
 */
export function useImportClients(): (csv: string, dryRun: boolean) => Promise<ClientImportReport> {
  const queryClient = useQueryClient()

  return async (csv, dryRun) => {
    const path = dryRun ? '/admin/clients/import?dry_run=1' : '/admin/clients/import'
    const report = await apiClient.postCsv<ClientImportReport>(path, csv)
    if (!dryRun && report.accepted) {
      void queryClient.invalidateQueries({ queryKey: clientKeys.lists() })
    }
    return report
  }
}
