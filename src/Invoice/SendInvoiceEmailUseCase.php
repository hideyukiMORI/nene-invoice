<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\Invoice\Pdf\InvoicePdfGeneratorInterface;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\Mailer\MailerInterface;
use NeneInvoice\Mailer\MailMessage;

/**
 * Sends a PDF of an issued invoice to the client's email address.
 *
 * Only issued / partially_paid invoices can be sent (draft has no number).
 * Requires the client to have an email address.
 */
final readonly class SendInvoiceEmailUseCase implements SendInvoiceEmailUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private ClientRepositoryInterface $clients,
        private CompanySettingsRepositoryInterface $companySettings,
        private InvoicePdfGeneratorInterface $pdfGenerator,
        private MailerInterface $mailer,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
        private string $fromName,
    ) {
    }

    /**
     * @param int|null $actorUserId authenticated user who triggered the send (null for system)
     *
     * @throws InvoiceNotFoundException
     * @throws InvoiceEmailException
     */
    public function execute(?int $actorUserId, int $invoiceId): void
    {
        $organizationId = $this->orgId->get();

        $prepared = $this->prepare($invoiceId);

        $company = $this->companySettings->find()
            ?? new CompanySettings(organizationId: $organizationId, legalName: $this->fromName);

        $withLines = new InvoiceWithLines($prepared->invoice, $prepared->lines, 0);
        $pdfData   = new Pdf\InvoicePdfData($withLines, $company, $prepared->client);
        $pdfBytes  = $this->pdfGenerator->generate($pdfData);

        $this->mailer->send(new MailMessage(
            toAddress: (string) $prepared->client->email,
            toName: $prepared->client->name,
            subject: $prepared->subject,
            bodyHtml: $prepared->bodyHtml,
            attachmentBytes: $pdfBytes,
            attachmentName: preg_replace('/[^A-Za-z0-9\-_]/', '_', $prepared->invoiceNumber) . '.pdf',
            attachmentMime: 'application/pdf',
        ));

        // Audit (ADR 0008): record the send as an auditable event. `after` is the
        // sanitized snapshot of the invoice content that was sent (proof of what
        // went out); `before` is null because no entity state changed.
        $this->audit->record(new AuditEvent(
            action: 'invoice.sent',
            entityType: 'invoice',
            entityId: $invoiceId,
            actorId: $actorUserId,
            organizationId: $organizationId,
            before: null,
            after: InvoiceResponse::toArray($prepared->invoice, $prepared->lines),
        ));
    }

    /**
     * Builds the email content (recipient / subject / body) without sending it or
     * recording an audit event (#626). Used by demo organizations, whose
     * fictitious `.example` clients are undeliverable; the send action shows this
     * in a modal instead of dispatching a message. No PDF is generated — the body
     * already notes that a PDF would be attached.
     *
     * The same sendability / client-email guards as {@see execute()} apply, so a
     * draft invoice or an emailless client is rejected identically.
     *
     * @throws InvoiceNotFoundException
     * @throws InvoiceEmailException
     */
    public function preview(int $invoiceId): SendInvoiceEmailPreview
    {
        $prepared = $this->prepare($invoiceId);

        return new SendInvoiceEmailPreview(
            recipient: (string) $prepared->client->email,
            subject: $prepared->subject,
            bodyHtml: $prepared->bodyHtml,
        );
    }

    /**
     * Runs the shared guards and assembles the recipient / subject / body that
     * both {@see execute()} and {@see preview()} rely on, so real sends and demo
     * previews never diverge.
     *
     * @throws InvoiceNotFoundException
     * @throws InvoiceEmailException
     */
    private function prepare(int $invoiceId): PreparedInvoiceEmail
    {
        $organizationId = $this->orgId->get();

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        if (
            $invoice->status !== InvoiceStatus::Issued
            && $invoice->status !== InvoiceStatus::PartiallyPaid
            && $invoice->status !== InvoiceStatus::Paid
        ) {
            throw InvoiceEmailException::notIssued($invoiceId);
        }

        $client = $this->clients->findById($invoice->clientId)
            ?? new Client(organizationId: $organizationId, name: '');

        if ($client->email === null || $client->email === '') {
            throw InvoiceEmailException::noClientEmail($invoiceId);
        }

        $lines = $this->lineItems->findByParent(LineItemParent::Invoice, $invoiceId);

        $company = $this->companySettings->find()
            ?? new CompanySettings(organizationId: $organizationId, legalName: $this->fromName);

        $invoiceNumber = $invoice->invoiceNumber ?? "INV-{$invoiceId}";
        $companyName   = $company->legalName;

        $bodyHtml = sprintf(
            '<p>%s 様</p><p>%s より請求書 %s をお送りします。</p><p>添付の PDF をご確認ください。</p><p>--<br>%s</p>',
            htmlspecialchars($client->name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'),
        );

        return new PreparedInvoiceEmail(
            invoice: $invoice,
            lines: $lines,
            client: $client,
            invoiceNumber: $invoiceNumber,
            subject: "請求書 {$invoiceNumber} — {$companyName}",
            bodyHtml: $bodyHtml,
        );
    }
}
