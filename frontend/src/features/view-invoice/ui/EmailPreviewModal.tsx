import { useId } from 'react'
import type { SendInvoiceEmailPreview } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { Button, InlineAlert, Stack, Text } from '@/shared/ui'

export interface EmailPreviewModalProps {
  preview: SendInvoiceEmailPreview
  onClose: () => void
}

/**
 * Shows the email a demo organization *would* have sent, instead of delivering
 * it (#626): demo clients use fictitious `.example` addresses that always bounce,
 * which would surface as a 502 and read like a product failure in a sales demo.
 * The notice makes clear no message was actually sent.
 *
 * `body_html` is rendered with `dangerouslySetInnerHTML`. This is safe here: the
 * markup is a fixed server-side template (SendInvoiceEmailUseCase) whose only
 * tags are literal `<p>` / `<br>`, and every interpolated value (client name,
 * company name, invoice number) is `htmlspecialchars`-escaped before it reaches
 * the client — there is no path for untrusted HTML to enter the string.
 */
export function EmailPreviewModal({ preview, onClose }: EmailPreviewModalProps) {
  const { t } = useTranslation()
  const titleId = useId()

  return (
    <div className="fixed inset-0 z-modal flex items-center justify-center bg-surface-overlay/70 px-inline-md">
      <button
        type="button"
        aria-label={t('common.actions.close')}
        className="absolute inset-0 size-full cursor-default"
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="relative w-full max-w-lg rounded-md border border-border bg-surface-raised px-inline-lg py-stack-lg shadow-md"
      >
        <Stack gap="md">
          <Text as="h2" id={titleId} variant="heading-sm">
            {t('admin.invoices.detail.emailPreviewTitle')}
          </Text>

          <InlineAlert tone="info" message={t('admin.invoices.detail.emailPreviewNotice')} />

          <Stack gap="sm">
            <div>
              <Text variant="muted" className="text-caption">
                {t('admin.invoices.detail.emailPreviewRecipient')}
              </Text>
              <Text className="break-all">{preview.recipient}</Text>
            </div>
            <div>
              <Text variant="muted" className="text-caption">
                {t('admin.invoices.detail.emailPreviewSubject')}
              </Text>
              <Text>{preview.subject}</Text>
            </div>
            <div>
              <Text variant="muted" className="text-caption">
                {t('admin.invoices.detail.emailPreviewBody')}
              </Text>
              <div
                className="rounded-md border border-border bg-surface px-inline-md py-stack-sm"
                dangerouslySetInnerHTML={{ __html: preview.body_html }}
              />
            </div>
          </Stack>

          <Stack direction="row" gap="sm" className="justify-end">
            <Button variant="primary" size="sm" onClick={onClose}>
              {t('common.actions.close')}
            </Button>
          </Stack>
        </Stack>
      </div>
    </div>
  )
}
