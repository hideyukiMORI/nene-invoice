import { Link, useNavigate } from 'react-router-dom'
import {
  invoiceStatusTone,
  useDownloadInvoicePdf,
  useSendInvoiceEmail,
  type InvoiceId,
} from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import {
  ActionError,
  Badge,
  Button,
  ErrorState,
  LineItemsTable,
  LoadingState,
  MutationError,
  Stack,
  Text,
  TotalRow,
  useToast,
} from '@/shared/ui'
import { useGenerateDownloadLink } from '../hooks/use-generate-download-link'
import { useViewInvoice } from '../hooks/use-view-invoice'

export interface ViewInvoiceProps {
  invoiceId: InvoiceId
}

/** Invoice detail: header summary, line items, and totals. */
export function ViewInvoice({ invoiceId }: ViewInvoiceProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { showToast } = useToast()
  const state = useViewInvoice(invoiceId)
  // Hooks must be called unconditionally — before any early return.
  const invoiceNumber = state.kind === 'ready' ? state.invoice.invoice_number : null
  const isIssued = state.kind === 'ready' && state.invoice.status !== 'draft'
  const pdf = useDownloadInvoicePdf(invoiceId, invoiceNumber)
  const sendEmail = useSendInvoiceEmail()
  const link = useGenerateDownloadLink(invoiceId, isIssued)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.invoices.loading')} />
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.invoices.detail.error')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  const invoice = state.invoice

  // 型3 success toast on send; reused by the 型2 "resend" recovery action.
  const sendInvoiceEmail = () => {
    sendEmail.mutate(invoiceId, {
      onSuccess: () => {
        showToast({
          tone: 'ok',
          title: t('admin.invoices.detail.emailSentTitle'),
          description:
            invoice.client_name !== null
              ? t('admin.invoices.detail.emailSentBody', { client: invoice.client_name })
              : t('admin.invoices.detail.emailSentBodyNoClient'),
        })
      },
    })
  }

  return (
    <Stack gap="lg">
      <Stack gap="sm">
        <Link to="/invoices" className="text-body text-accent">
          ← {t('admin.invoices.detail.backToList')}
        </Link>
        <div className="flex flex-wrap items-start justify-between gap-stack-sm">
          <Text as="h1" variant="heading-md" className="num">
            {invoice.invoice_number ?? t('admin.invoices.detail.notIssued')}
          </Text>
          <div className="flex flex-wrap items-start gap-inline-sm">
            {pdf.canDownload && (
              <Stack gap="sm">
                <Button onClick={pdf.download} disabled={pdf.isDownloading}>
                  {pdf.isDownloading
                    ? t('admin.invoices.detail.downloadingPdf')
                    : t('admin.invoices.detail.downloadPdf')}
                </Button>
                <MutationError message={pdf.errorMessage} />
              </Stack>
            )}
            {isIssued && (
              <Stack gap="sm">
                <Button onClick={sendInvoiceEmail} disabled={sendEmail.isPending}>
                  {sendEmail.isPending
                    ? t('admin.invoices.detail.sendingEmail')
                    : t('admin.invoices.detail.sendEmail')}
                </Button>
                {sendEmail.isError && (
                  <div className="max-w-md">
                    <ActionError
                      title={t('admin.invoices.detail.emailErrorTitle')}
                      description={t('admin.invoices.detail.emailErrorBody')}
                      actions={[
                        {
                          label: t('admin.invoices.detail.emailRetry'),
                          variant: 'solid',
                          onClick: sendInvoiceEmail,
                        },
                        {
                          label: t('admin.invoices.detail.emailCheckClient'),
                          variant: 'outline',
                          onClick: () => {
                            void navigate(`/clients/${String(invoice.client_id)}/edit`)
                          },
                        },
                      ]}
                      onClose={() => {
                        sendEmail.reset()
                      }}
                      closeLabel={t('common.actions.close')}
                    />
                  </div>
                )}
              </Stack>
            )}
            {link.canGenerate && (
              <Stack gap="sm">
                <Button onClick={link.generate} disabled={link.isGenerating}>
                  {link.isGenerating
                    ? t('admin.invoices.detail.generatingLink')
                    : t('admin.invoices.detail.generateLink')}
                </Button>
                {link.downloadUrl !== null && (
                  <Stack gap="sm">
                    <Text variant="muted" className="break-all text-caption">
                      {`${window.location.origin}${link.downloadUrl}`}
                    </Text>
                    <Stack direction="row" gap="sm">
                      <Button onClick={link.copy}>
                        {link.copied
                          ? t('admin.invoices.detail.linkCopied')
                          : t('admin.invoices.detail.linkCopy')}
                      </Button>
                      {link.expiresAt !== null && (
                        <Text variant="muted">
                          {t('admin.invoices.detail.linkExpiry', { expiresAt: link.expiresAt })}
                        </Text>
                      )}
                    </Stack>
                  </Stack>
                )}
                <MutationError message={link.errorMessage} />
              </Stack>
            )}
          </div>
        </div>
        <Stack direction="row" gap="md">
          <Badge tone={invoiceStatusTone[invoice.status]}>
            {t(`admin.invoices.status.${invoice.status}`)}
          </Badge>
          {invoice.is_overdue && <Badge tone="danger">{t('admin.invoices.status.overdue')}</Badge>}
          {invoice.is_qualified_invoice && (
            <Badge tone="brand">{t('admin.invoices.detail.qualified')}</Badge>
          )}
          {invoice.issued_at !== null && (
            <Text variant="muted">
              {t('admin.invoices.detail.issuedAt')}: {invoice.issued_at}
            </Text>
          )}
          {invoice.due_at !== null && (
            <Text variant="muted">
              {t('admin.invoices.detail.dueAt')}: {invoice.due_at}
            </Text>
          )}
        </Stack>
      </Stack>

      <LineItemsTable items={invoice.line_items} />

      <Stack gap="sm" className="ml-auto w-64">
        <TotalRow
          label={t('admin.invoices.detail.subtotal')}
          value={formatYen(invoice.subtotal_cents)}
        />
        <TotalRow label={t('admin.invoices.detail.tax')} value={formatYen(invoice.tax_cents)} />
        <TotalRow label={t('admin.invoices.detail.total')} value={formatYen(invoice.total_cents)} />
        {invoice.outstanding_cents !== null && (
          <TotalRow
            label={t('admin.invoices.detail.outstanding')}
            value={formatYen(invoice.outstanding_cents)}
          />
        )}
      </Stack>
    </Stack>
  )
}
